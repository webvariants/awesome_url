<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Awesome URL',
	'description' => 'Speaking URLs, easy configuration',
	'category' => 'fe',
	'version' => '0.0.1',
	'state' => 'alpha',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearcacheonload' => 1,
	'author' => 'Martin Schnabel',
	'author_email' => 'martin.schnabel@webvariants.de',
	'author_company' => 'webvariants.de',
	'constraints' =>
	array(
		'depends' =>
		array(
			'php' => '5.3.0-0.0.0',
			'typo3' => '6.2.1-0.0.0',
		),
		'conflicts' =>
		array(
			1 => 'cooluri',
			2 => 'realurl',
			3 => 'simulatestatic',
		),
		'suggests' =>
		array(
		),
	),
);

