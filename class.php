<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/**
 * Класс находит графики работы и всех докторов привязанных к ним
 * Так же ищет и мерджит c данные докторов, у которых нет графика работы
 * Выводит в шаблоне компонента
 *
 * info@dev-consult.ru
 * dev-consult.ru
 * version 1.0
 */
use Bitrix\Main\Loader;
use Bitrix\Crm\Entity\Deal;
use Bitrix\Main\Engine\Action;
use Bitrix\Timeman\Service\DependencyManager;
use Bitrix\Timeman\Form\Schedule\ScheduleForm;
use Bitrix\Timeman\Model\Schedule\ScheduleTable;

class DoctorsSchedule extends \CBitrixComponent
{
    const DOCTORS_DEPARTMENT_ID = 3;
    const DEPARTMENTS_IBLOCK_ID = 5;

    /**
     * Метод выводит полученные результат отсортированный разбитый по городу и отсотированный по имени
     *
     * @return array
     */
    private function devideResultsByCityAndEmptines() {
       $arResultArray = [
           'MOSCOW' => [],
           'PETERSBURG' => [],
           'EMPTY_SCHEDULE' => []
       ];
       $workDays = $this->getUsersDataMergeWithSchedule();

        foreach ( $workDays as $key => $value ) {
            $dataInsideRoundBrackets = preg_match("~(?<=\()[^\(\]]+(?=\))~", $value['SCHEDULE_NAME'], $matches);
            if( count($matches)>0 ) {
                $additionalData = explode("|", $matches[0]);
                $workDays[$key]['ADDITIONAL_SCHEDULE_DATA'] = $additionalData;
            } else {
                $workDays[$key]['ADDITIONAL_SCHEDULE_DATA'] = "";
            }
        }


       foreach ( $workDays as $key => $value ) {
           if( strlen($value['OUTPUT_HTML_FOR_WORK_DAYS'])>0 ) {
                if( $value['CITY_NAME'] == 'Санкт-Петербург') {
                    $arResultArray['PETERSBURG'][] = $value;
                } else {
                    $arResultArray['MOSCOW'][] = $value;
                }
           } else {
               $arResultArray['EMPTY_SCHEDULE'][] = $value;
           }
       }

       foreach ( $arResultArray as $arResultArrayKey => $arResultArrayValue ) {
           usort($arResultArray[$arResultArrayKey], function($a, $b) {
               return $a['USER_DATA']['NAME'] > $b['USER_DATA']['NAME'];
           });
       }

       return $arResultArray;
    }

    /**
     * метод находит данные пользователей
     *
     * @param array $userIds
     * @return array
     */
    private function getUsersDataMergeWithSchedule() {
        $activeWorkTimeData = $this->getDoctorsWithWorksDays();
        $usersIds = array_keys( $activeWorkTimeData );

        $dbUsers = \Bitrix\Main\UserTable::getList([
            'select' => ['ID','NAME','EMAIL'],
            'filter' => ['ID' => $usersIds]
        ]);

        $existedUsersIds = [];
        while ($user = $dbUsers -> Fetch() ) {
            $existedUsersIds[] = $user['ID'];
            $activeWorkTimeData[$user['ID']]['USER_DATA'] = $user;

            $petersburgIndication = strpos( strtolower($user['NAME']), "Питер" );
            if( $petersburgIndication !== false ) {
                $activeWorkTimeData[$user['ID']]['CITY_NAME'] = "Санкт-Петербург";
            } else {
                $activeWorkTimeData[$user['ID']]['CITY_NAME'] = "Москва";
            }
        }

        usort( $activeWorkTimeData, function($a, $b) {
            return $a['CITY_NAME'] > $b['CITY_NAME'];
        } );

        $doctors = $this->getDoctorsWithoutSchedule();

        $additionalEmptyDoctors = [];
        foreach ( $doctors as $doctor ) {
            if( !in_array( $doctor['ID'], $existedUsersIds ) ) {
                $additionalEmptyDoctors[$doctor['ID']]['USER_DATA'] = $doctor;
            }
        }

        $activeWorkTimeData = array_merge( $activeWorkTimeData, $additionalEmptyDoctors );


        return $activeWorkTimeData;
    }

    /**
     * метод возвращает пользователей и их рабочие дни из графика
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getDoctorsWithWorksDays() {
        $workdaysOfDoctors = [];

        \CModule::IncludeMOdule('timeman');

        // находим рабочие графики
        $scheduleList = ScheduleTable::getList([
            'select' => ['ID','NAME']
        ])->fetchAll();

        $scheduleData = [];
        foreach ( $scheduleList as $scheduleListKey => $scheduleListValue ) {
            $scheduleData[$scheduleListValue['ID']] = $scheduleListValue['NAME'];
        }
        $scheduleList = array_column($scheduleList, 'ID');

        $allWorktimeData = [];
        $allUsers = [];
        foreach ($scheduleList as $scheduleId) {
            $scheduleRepository = DependencyManager::getInstance()->getScheduleRepository();
            $schedule = $scheduleRepository->findByIdWith($scheduleId, [
                'SHIFTS',  'USER_ASSIGNMENTS'
            ]);

            $provider = DependencyManager::getInstance()->getScheduleProvider();
            $users = $provider->findActiveScheduleUserIds($schedule);
            $scheduleForm = new ScheduleForm($schedule);

            $shiftTemplate = new \Bitrix\Timeman\Form\Schedule\ShiftForm();
            $shiftFormWorkDays = [];
            foreach (array_merge([$shiftTemplate], $scheduleForm->getShiftForms()) as $shiftIndex => $shiftForm)
            {
                $shiftFormWorkDays[] = array_map('intval', str_split($shiftForm->workDays));
            }

            $worktime = [];
            foreach ($users as $userId)
            {
                $allUsers[] = $userId;
                foreach($shiftFormWorkDays as $key => $value) {
                    if( $value[0] !== 0 ) {
                        $worktime[$userId] = $value;
                        $worktime[$userId]['SCHEDULE_NAME'] = $scheduleData[$scheduleId];
                    }
                }
            }

            $allWorktimeData[] = $worktime;
        }

        $activeWorkTimeData = [];
        foreach ( $allWorktimeData as $key => $workTime ) {
            if( count($workTime) > 0 ) {
                foreach ( $workTime as $workTimeKey => $workTimeValue ) {
                    $activeWorkTimeData[$workTimeKey] = $workTimeValue;
                }
            }
        }

        $weekDaysNames = [
            '1' => 'Пн',
            '2' => 'Вт',
            '3' => 'Ср',
            '4' => 'Чт',
            '5' => 'Пт',
            '6' => 'Сб',
            '7' => 'Вс',
        ];

        $weekDaysResult = $this->getSixDaysFromCurrentDay();
        $weekDays = [];
        foreach ( $weekDaysResult as $value) {
            $weekDays[$value['WEEK_DAY']] = [
                'WEEK_DAY' => $weekDaysNames[$value['WEEK_DAY']],
                "D_DAY" => $value['D_DAY']
            ];
        }

        $dayofweek = date('w', strtotime( date('Y-m-d') ));

        foreach ( $activeWorkTimeData  as $activeWorkTimeKey => $aktiveWorkTimeValue ) {

            $weekHtmlOutput = "<div class='doctor-schedule'>";
            foreach ( $weekDays as $key => $value ) {
                $class = "";
                // определим является ли день рабочим или выходным и зададим нужный класс
                if( in_array( $key, $aktiveWorkTimeValue ) ) {
                    $class = "doctor-schedule__workday";
                    $title = "Рабочий день";
                } else {
                    $class = "doctor-schedule__holiday";
                    $title = "Выходной";
                }

                // определим является ли день сегодняшним и добавим класс если это так
                if( $key == $dayofweek ) {
                    $class .= " doctor-schedule__today";
                    $title .= " cегодня";
                }

                $format = "<span title='%s' class='%s'>%s <br/> %s</span>";

                $weekHtmlOutput .= sprintf($format, $title, $class, $weekDays[$key]['D_DAY'], $weekDays[$key]['WEEK_DAY']);

            }
            $weekHtmlOutput .= "</div>";

            $activeWorkTimeData[$activeWorkTimeKey]['OUTPUT_HTML_FOR_WORK_DAYS'] =  $weekHtmlOutput;
        }

        return $activeWorkTimeData;
    }

    /**
     * Метод находит дни месяца 6 дней от текущего дня
     *
     * @return array
     */
    private function getSixDaysFromCurrentDay() {
        $currentDateDayofweek = date('w', strtotime(date('Y-m-d')) );
        $dayOfMonth = date('d',strtotime(date('Y-m-d')) );

        $dateArray[] = [
            "WEEK_DAY" => $currentDateDayofweek,
            "D_DAY" => $dayOfMonth
        ];
        for( $i=1; $i<7; $i++ ){
            $dayofweek = date('w', strtotime(date('Y-m-d').'+'.$i.'day'));
            if( $dayofweek == 0 ) {
                $dayofweek = 7;
            }
            $dayOfMonth = date('d',strtotime(date('Y-m-d').'+'.$i.'day'));
            $dateArray[] = [
                "WEEK_DAY" => $dayofweek,
                "D_DAY" => $dayOfMonth
            ];
        };

        return $dateArray;
    }

    /**
     * Метод находит идентификаторы всех отделов отдела Медцентр
     *
     * @return array
     */
    private function getAllInternalDepartmentsOfMedcenter() {
        $departemntIds = [];
        $dbDepartment = \Bitrix\Iblock\SectionTable::getList([
            'select' => [
                'ID'
            ],
            'filter' => [
                'IBLOCK_ID' => self::DEPARTMENTS_IBLOCK_ID,
                'IBLOCK_SECTION_ID' => self::DOCTORS_DEPARTMENT_ID
            ]
        ]);

        while ( $department = $dbDepartment -> Fetch() ) {
            $departemntIds[] = $department['ID'];
        }

        return $departemntIds;
    }

    /**
     * Метод возвращает массив всех докторов
     * Инфоблок Подразделения - IBLOCK_ID = 5
     * Раздел (Отдел) Медцентры - ID = 3
     *
     * @return array
     */
    private function getDoctorsWithoutSchedule() {
        $doctors = [];
        $departmentIds = $this->getAllInternalDepartmentsOfMedcenter();
        $dbDoctors = \Bitrix\Main\UserTable::getList([
            'select' => ['ID','NAME','EMAIL'],
            'filter' => [
                'UF_DEPARTMENT' => $departmentIds
            ]
        ]);
        while ( $doctor = $dbDoctors -> Fetch() ) {
            $doctors[$doctor['ID']] = $doctor;
        }

        return $doctors;
    }

    public function executeComponent()
    {
        $this->arResult['DOCTORS_SCHEDULES'] = $this->devideResultsByCityAndEmptines();
        $this->includeComponentTemplate();
    }
}