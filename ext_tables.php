<?php

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

t3lib_extMgm::addStaticFile($_EXTKEY,'static/akismet/', 'Akismet');

$tempColumns = array(
	'tx_timtabakismet_isspam' => array(
		'exclude' => 0,
		'label' => 'LLL:EXT:timtab_akismet/locallang_db.xml:tx_veguestbook_entries.tx_timtabakismet_isspam',
		'config' => array(
			'type' => 'check',
		)
	)
);


t3lib_div::loadTCA('tx_veguestbook_entries');
t3lib_extMgm::addTCAcolumns('tx_veguestbook_entries', $tempColumns, true);
t3lib_extMgm::addToAllTCAtypes('tx_veguestbook_entries', 'tx_timtabakismet_isspam;;;;1-1-1');

?>