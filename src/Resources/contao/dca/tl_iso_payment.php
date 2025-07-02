<?php

declare(strict_types=1);

namespace {

    /**
     * Rhyme Contao Isotope Paytrace Bundle
     *
     * Copyright (c) 2025 Rhyme.Digital
     *
     * @license GPLv3
     */

    //use Contao\Controller;
    //Controller::loadLanguage('tl_iso_payment');

    /**
     * Palettes
     */
    $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['rhyme_paytrace'] = '{type_legend},name,label,type;{note_legend:hide},note;{config_legend},new_order_status,paytrace_cc_types,requireCCV,minimum_total,maximum_total,countries,shipping_modules,product_types,product_types_condition,config_ids;{gateway_legend},paytrace_login,paytrace_password,paytrace_integrator_id,paytrace_trans_type;{price_legend:hide},price,tax_class;{expert_legend:hide},guests,protected;{enabled_legend},debug,enabled';

    /**
     * Fields
     */
    $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paytrace_cc_types'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_cc_types'],
        'exclude' => true,
        'inputType' => 'select',
        'options' => array('mc', 'visa', 'amex', 'discover', 'diners'),
        'reference' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands'],
        'eval' => array('mandatory' => true, 'multiple' => true, 'tl_class' => 'w50 clr w50h', 'chosen' => true),
        'sql' => "text NULL"
    );

    $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paytrace_username'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_username'],
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paytrace_password'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_password'],
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paytrace_integrator_id'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_integrator_id'],
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'hideInput' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paytrace_trans_type'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_trans_type'],
        'exclude' => true,
        'inputType' => 'select',
        'options' => array('AUTH_ONLY', 'AUTH_CAPTURE'),
        'reference' => &$GLOBALS['TL_LANG']['tl_iso_payment'],
        'eval' => array('mandatory' => true),
        'sql' => "varchar(32) NOT NULL default ''"
    );

}