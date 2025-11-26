<?php

/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

declare(strict_types=1);

namespace {

    use Isotope\Backend;
    use Isotope\Model\Payment;

    $t = Payment::getTable();
    $dca = &$GLOBALS['TL_DCA'][$t];

    /**
     * Palettes
     */
    $dca['palettes']['rhyme_paytrace'] = '{type_legend},name,label,type;{note_legend:hide},note;{config_legend},new_order_status,paytrace_cc_types,requireCCV,minimum_total,maximum_total,countries,shipping_modules,product_types,product_types_condition,config_ids;{gateway_legend},paytrace_username,paytrace_password,paytrace_integrator_id,paytrace_trans_type;{price_legend:hide},price,tax_class;{expert_legend:hide},guests,protected;{enabled_legend},debug,enabled';

    /**
     * Fields
     */
    $dca['fields']['paytrace_cc_types'] = array
    (
        'exclude' => true,
        'inputType' => 'select',
        'options' => array('mc', 'visa', 'amex', 'discover', 'diners'),
        'reference' => &$GLOBALS['TL_LANG']['tl_iso_payment']['paytrace_brands'],
        'eval' => array('mandatory' => true, 'multiple' => true, 'tl_class' => 'w50 clr w50h', 'chosen' => true),
        'sql' => "text NULL"
    );

    $dca['fields']['paytrace_username'] = array
    (
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $dca['fields']['paytrace_password'] = array
    (
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'hideInput' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $dca['fields']['paytrace_integrator_id'] = array
    (
        'exclude' => true,
        'inputType' => 'text',
        'eval' => array('mandatory' => true, 'hideInput' => true, 'maxlength' => 255),
        'sql' => "varchar(255) NOT NULL default ''"
    );

    $dca['fields']['paytrace_trans_type'] = array
    (
        'exclude' => true,
        'inputType' => 'select',
        'options' => array('AUTH_ONLY', 'AUTH_CAPTURE'),
        'reference' => &$GLOBALS['TL_LANG']['tl_iso_payment'],
        'eval' => array('mandatory' => true),
        'sql' => "varchar(32) NOT NULL default ''"
    );

    $dca['fields']['customTpl'] = $dca['fields']['customTpl'] ?? array
    (
        'exclude'               => true,
        'inputType'             => 'select',
        'options_callback'      => function() {
            return Backend::getTemplates('iso_payment_');
        },
        'eval'                  => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
        'sql'                   => "varchar(64) NOT NULL default ''"
    );

}