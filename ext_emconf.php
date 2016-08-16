<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Awesome URL',
    'description' => 'Speaking URLs, easy configuration',
    'category' => 'fe',
    'version' => '0.0.2',
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearcacheonload' => 1,
    'author' => 'Martin Schnabel',
    'author_email' => 'martin.schnabel@webvariants.de',
    'author_company' => 'webvariants.de',
    'constraints' => array(
        'depends' => array(
            'php' => '5.3.0-0.0.0',
            'typo3' => '6.2.0-7.6.99',
        ),
        'conflicts' => array(
            1 => 'cooluri',
            2 => 'realurl',
            3 => 'simulatestatic',
        ),
        'suggests' => array(
        ),
    ),
);
