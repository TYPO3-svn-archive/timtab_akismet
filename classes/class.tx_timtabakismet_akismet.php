<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_div::getIndpEnv('TYPO3_DOCUMENT_ROOT') . '/typo3/interfaces/interface.localrecordlist_actionsHook.php');
require_once(t3lib_extMgm::extPath('timtab_akismet') . 'lib/Akismet.class.php');


/**
 * Akismet Integration for TYPO3, currently supporting EXT:ve_guestbook
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage timtab_akismet
 */
class tx_timtabakismet_Akismet implements localRecordList_actionsHook {

	protected $configuration = array();

	/**
	 * constructor for class tx_timtabakismet_Akismet
	 */
	public function __construct() {
		if ($GLOBALS['TSFE']) {
				// FE
			$this->configuration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_timtab.']['akismet.'];
		}
	}

	protected function initConfiguration() {
		$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
		$rootLine = $pageSelect->getRootLine(t3lib_div::_GET('id'));

		$tmpl = t3lib_div::makeInstance('t3lib_tsparser_ext');
		$tmpl->tt_track = false; // Do not log time-performance information
		$tmpl->init();
		$tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
		$tmpl->generateConfig();

		list($akismetSetup) = $tmpl->ext_getSetup($tmpl->setup, 'plugin.tx_timtab.akismet');
		$this->configuration = $akismetSetup;
	}

	public function preEntryInsertProcessor(array $comment, $parentObject) {
		$akismet = new Akismet(
			$this->configuration['url'],
			$this->configuration['apiKey']
		);

		$akismet->setCommentAuthor($comment['firstname'] . ' ' . $comment['lastname']);
		$akismet->setCommentAuthorEmail($comment['email']);
		$akismet->setCommentAuthorURL($comment['homepage']);
		$akismet->setCommentContent($comment['entry']);
		$akismet->setPermalink(t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
		$akismet->setCommentType('comment');
		$akismet->setUserIP($comment['remote_addr']);

		$isSpam = $akismet->isCommentSpam();

		if ($isSpam) {
			$comment = $this->handleSpam($comment);
		}

		return $comment;
	}

	protected function handleSpam(array $comment) {
			// mark as spam
		$comment['tx_timatbakismet_isspam']	= 1;

		switch ($this->configuration['spamAction']) {
			case 'keep':
					// do nothing, keep the comment despite being spam
				break;
			case 'delete':
				$comment['deleted'] = 1;
				break;
			case 'dismiss':
					// TYPO3 won't save an empty record
				$comment = array();
				break;
			case 'hide':
			default:
				$comment['hidden'] = 1;
		}

		return $comment;
	}

	/**
	 * modifies Web>List control icons of a displayed row
	 *
	 * @param	string		the current database table
	 * @param	array		the current record row
	 * @param	array		the default control-icons to get modified
	 * @param	object		Instance of calling object
	 * @return	array		the modified control-icons
	 */
	public function makeControl($table, $row, $cells, &$parentObject) {
		if ($table == 'tx_veguestbook_entries') {
			$pageId = t3lib_div::_GET('id');
			$akismetCell = '';
			$locallang = $GLOBALS['LANG']->includeLLfile(t3lib_div::getFileAbsFileName('EXT:timtab_akismet/locallang_db.xml'), true);

			if ($row['tx_timtabakismet_isspam']) {
					// currently marked as spam, mark it as ham
				$params = '&data['.$table.']['.$row['uid'].'][tx_timtabakismet_isspam]=0&id=' . $pageId;
				$akismetCell = '<a href="#" onclick="' . htmlspecialchars('return jumpToUrl(\'' . $GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');') . '">' .
					'<img' . t3lib_iconWorks::skinImg('/' . t3lib_extMgm::siteRelPath('timtab_akismet'), 'gfx/ham.gif', 'width="16" height="16"').' title="' . $GLOBALS['LANG']->getLL('tx_veguestbook_entries.tx_timtabakismet_markNoSpam', $locallang, true) . '" alt="" />' .
					'</a>';
			} else {
					// currently marked as ham, mark it as spam
				$params = '&data['.$table.']['.$row['uid'].'][tx_timtabakismet_isspam]=1&id=' . $pageId;
				$akismetCell = '<a href="#" onclick="'.htmlspecialchars('return jumpToUrl(\''.$GLOBALS['SOBE']->doc->issueCommand($params, -1) . '\');') . '">' .
					'<img' . t3lib_iconWorks::skinImg('/' . t3lib_extMgm::siteRelPath('timtab_akismet'), 'gfx/spam.gif', 'width="16" height="16"') . ' title="' . $GLOBALS['LANG']->getLL('tx_veguestbook_entries.tx_timtabakismet_markSpam', $locallang, true) . '" alt="" />' .
					'</a>';
			}

				// insert the akismet button after the delete button
			$tempCells = array();
			foreach($cells as $key => $val) {
				if ($key == 'delete') {
					$tempCells[$key] = $val;
					$tempCells['tx_timtabakismet'] = $akismetCell;
				} else {
					$tempCells[$key] = $val;
				}
			}

				// remove the move left cell to prevent the control field from
				// wrapping into the next line, it's not needed anyway
			unset($tempCells['moveLeft']);
			$cells = $tempCells;
		}

		return $cells;
	}

	/**
	 * Hook for sending back information to akismet if an entry is a false
	 * positive false or a missed spam comment
	 *
	 * @param unknown_type $status
	 * @param unknown_type $table
	 * @param unknown_type $id
	 * @param unknown_type $fieldArray
	 * @param unknown_type $pObj
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, $parentObject) {

			//only on change of the isspam icon
		if($fieldArray['tx_timtabakismet_isspam'] && $table == 'tx_veguestbook_entries') {
			if (empty($this->configuration)) {
				$this->initConfiguration();
			}

				//Get the original comment
			$comment = t3lib_BEfunc::getRecord('tx_veguestbook_entries', $id);

			$akismet = new Akismet(
				$this->configuration['url'],
				$this->configuration['apiKey']
			);

			$akismet->setCommentAuthor($comment['firstname'] . ' ' . $comment['lastname']);
			$akismet->setCommentAuthorEmail($comment['email']);
			$akismet->setCommentAuthorURL($comment['homepage']);
			$akismet->setCommentContent($comment['entry']);
			$akismet->setPermalink(t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
			$akismet->setCommentType('comment');
			$akismet->setUserIP($comment['remote_addr']);

				// a false positive
			if ($fieldArray['tx_timtabakismet_isspam'] == 0) {
				$akismet->submitHam();
				// failed to detect spam
			} elseif ($fieldArray['tx_timtabakismet_isspam'] == 1) {
				$akismet->submitSpam();
			}
		}
	}

	/**
	 * modifies Web>List clip icons (copy, cut, paste, etc.) of a displayed row
	 *
	 * @param	string		the current database table
	 * @param	array		the current record row
	 * @param	array		the default clip-icons to get modified
	 * @param	object		Instance of calling object
	 * @return	array		the modified clip-icons
	 */
	public function makeClip($table, $row, $cells, &$parentObject) {
		return $cells;
	}


	/**
	 * modifies Web>List header row columns/cells
	 *
	 * @param	string		the current database table
	 * @param	array		Array of the currently displayed uids of the table
	 * @param	array		An array of rendered cells/columns
	 * @param	object		Instance of calling (parent) object
	 * @return	array		Array of modified cells/columns
	 */
	public function renderListHeader($table, $currentIdList, $headerColumns, &$parentObject) {
		return $headerColumns;
	}


	/**
	 * modifies Web>List header row clipboard/action icons
	 *
	 * @param	string		the current database table
	 * @param	array		Array of the currently displayed uids of the table
	 * @param	array		An array of the current clipboard/action icons
	 * @param	object		Instance of calling (parent) object
	 * @return	array		Array of modified clipboard/action icons
	 */
	public function renderListHeaderActions($table, $currentIdList, $cells, &$parentObject) {
/*
		if ($table == 'tx_veguestbook_entries' && $parentObject->clipObj->current!='normal') {
			$locallang = $GLOBALS['LANG']->includeLLfile(t3lib_div::getFileAbsFileName('EXT:timtab_akismet/locallang_db.xml'), true);

			$akismetMarkSpamCell = $parentObject->linkClipboardHeaderIcon(
				'<img' . t3lib_iconWorks::skinImg('/' . t3lib_extMgm::siteRelPath('timtab_akismet'), 'gfx/spam.gif', 'width="16" height="16"')
				. ' title="' . $GLOBALS['LANG']->getLL('tx_veguestbook_entries.tx_timtabakismet_markSpam', $locallang, true)
				. '" alt="" />',
				$table,
				'markSpam'
			);

			$akismetMarkHamCell = $parentObject->linkClipboardHeaderIcon(
				'<img' . t3lib_iconWorks::skinImg('/' . t3lib_extMgm::siteRelPath('timtab_akismet'), 'gfx/ham.gif', 'width="16" height="16"')
				. ' title="' . $GLOBALS['LANG']->getLL('tx_veguestbook_entries.tx_timtabakismet_markNoSpam', $locallang, true)
				. '" alt="" />',
				$table,
				'markNoSpam'
			);

			$cells['tx_timtabakismet_spam'] = $akismetMarkSpamCell;
			$cells['tx_timtabakismet_ham'] = $akismetMarkHamCell;
		}
*/

		return $cells;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_akismet/classes/class.tx_timtabakismet_akismet.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_akismet/classes/class.tx_timtabakismet_akismet.php']);
}

?>