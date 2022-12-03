<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;

$arComponentDescription = array(
    'NAME' => Loc::getMessage('DOCTORS_SCHEDULE_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('DOCTORS_SCHEDULE_COMPONENT_DESCRIPTION'),
    'PATH' => [
        'ID' => 'devconsult',
        'NAME' => Loc::getMessage('DEVCONSULT_COMPANY_NAME')
    ]
);