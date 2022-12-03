<?php if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 * @var $arResult
 */
use \Bitrix\Main\Ui\Extension;
Extension::load('ui.bootstrap4');
?>
<table class="table table-hover table__doctors-schedule">
    <?php foreach( $arResult['DOCTORS_SCHEDULES'] as $cityKey => $cityValue ): ?>
        <?php foreach( $cityValue as $cityValueKey => $cityValueValue): ?>
            <tr>
                <td class="doctors__td_first">
                    <b><?= $cityValueValue['USER_DATA']['NAME'] ?></b>
                </td>
                <td class="doctors__td_second">
                    <div class="doctors__td_additional-data">
                        <? if( count( $cityValueValue['ADDITIONAL_SCHEDULE_DATA'] )>0 ): ?>
                            <?php foreach ($cityValueValue['ADDITIONAL_SCHEDULE_DATA'] as $arData): ?>
                                <span>
                                    <?= $arData; ?>
                                </span>
                            <?php endforeach; ?>
                        <? endif; ?>
                    </div>
                </td>
                <td class="doctors__td_third">
                    <?= $cityValueValue['OUTPUT_HTML_FOR_WORK_DAYS']; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
</table>
