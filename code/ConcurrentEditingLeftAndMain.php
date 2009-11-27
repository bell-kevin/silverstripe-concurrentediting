<?php
/**
 * @package concurrentediting
 */
class ConcurrentEditingLeftAndMain extends LeftAndMainDecorator {
	static $allowed_actions = array(
		'concurrentEditingPing',
	);
	
	static $edit_timeout = 35;
	
	
	function init() {
		parent::init();
		Requirements::javascript('concurrentediting/javascript/ConcurrentEditing.js');
	}
	
	function concurrentEditingPing() {
		if (!isset($_REQUEST['ID'])) die('no id passed');
		
		$page = $this->owner->getRecord($_REQUEST['ID']);
		if (!$page) {
			// Page has not been found
			$return = array('status' => 'not_found');
		} elseif ($page->getIsDeletedFromStage()) {
			// Page has been deleted from stage
			$return = array('status' => 'deleted');
		} else {
			// Mark me as editing if I'm not already
			$page->UsersCurrentlyEditing()->add(Member::currentUser());
			DB::query("UPDATE SiteTree_UsersCurrentlyEditing SET LastPing = '".date('Y-m-d H:i:s')."'
				WHERE MemberID = ".Member::currentUserID()." AND SiteTreeID = {$page->ID}");
			
			// Page exists, who else is editing it?
			$names = array();
			foreach($page->UsersCurrentlyEditing() as $user) {
				if ($user->ID == Member::currentUserId()) continue;
				$names[] = trim($user->FirstName . ' ' . $user->Surname);
			}
			$return = array('status' => 'editing', 'names' => $names);
			
			// Has it been published since the CMS first loaded it?
			$usersSaveCount = isset($_REQUEST['SaveCount']) ? $_REQUEST['SaveCount'] : $page->SaveCount;
			if ($usersSaveCount < $page->SaveCount) {
				$return = array('status' => 'not_current_version');
			}
		}
		
		// Delete pings older than *timeout* from the cache...
		DB::query("DELETE FROM SiteTree_UsersCurrentlyEditing WHERE LastPing < '".date('Y-m-d H:i:s', time()-self::$edit_timeout)."'");
		
		return Convert::array2json($return);
	}
	
	function onAfterSave(&$record) {
		$record->SaveCount++;
		$record->writeWithoutVersion();
		FormResponse::add('CurrentPage.setSaveCount('.$record->SaveCount.');');
	}
}