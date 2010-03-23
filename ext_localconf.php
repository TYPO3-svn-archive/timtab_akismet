<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

	// registering hooks
$TYPO3_CONF_VARS['EXTCONF']['ve_guestbook']['preEntryInsertHook'][] = 'EXT:timtab_akismet/classes/class.tx_timtabakismet_akismet.php:tx_timtabakismet_Akismet';
$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/class.db_list_extra.inc']['actions']['tx_timtabakismet'] = 'EXT:timtab_akismet/classes/class.tx_timtabakismet_akismet.php:tx_timtabakismet_Akismet';
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:timtab_akismet/classes/class.tx_timtabakismet_akismet.php:&tx_timtabakismet_Akismet';

?>