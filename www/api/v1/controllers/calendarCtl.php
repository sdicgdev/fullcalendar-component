<?php
class CalendarCtl
{


	/**
	*	CalendarCtl::get provides an interface for aquiring calendars by means of
	*	the calendar-id(s) passed to the method.
	*
	*	@param $id  String of calendar-ids seperated by '/'
	*	@return JSON encode array of Calendar(s)
	*
	*	@todo HTTP CONDITIONAL GET
	*/
	function get($id) {
		$calendars = Calendar::loadActive(split('/', $id));
	 	echo json_encode($calendars);
	}

	/**
	* 	CalendarCtl::getAll provides an interface for aquiring all calendars
	* 
	*	@return JSON encode array of Calendar(s)
	*
	* 	@todo get all calendars per signed in user
	*/
	function getAll ($id = null) {
		$authUserID = Authorize:: sharedInstance()->userID();

	 	$cals = (object) array_merge((array) Calendar::loadUserOrgCalendars($authUserID), (array) Calendar::loadUserSubscriptions($authUserID));
		$dataRay = array();
		foreach($cals as $calendar){
			$calendar->subscribed = false;
			$calendar->color = '';
			$colorResult = Calendar:: genericQuery("SELECT
					color, view_setting
				FROM
					calendar_subs
				WHERE
					calendar_id='{$calendar->calendar_id}'
				AND
					user_id='$authUserID'");
			if(sizeof($colorResult) > 0){
				$calendar->color = $colorResult[0]->color;
				$calendar->subscribed = true;
				$calendar->viewing = ($colorResult[0]->view_setting)? true:false;
			}
			$publicResult = Calendar:: genericQuery("SELECT
					org_id
				FROM
					public_calendars
				WHERE
					calendar_id='{$calendar->calendar_id}'
				");
			if(sizeof($publicResult) > 0){
				$calendar->public = true;
				$calendar->org_id = $publicResult[0]->org_id;
			}else{
				$calendar->public = false;
			}
			$calendar->active = ($calendar->active)? true:false;
			$calendar->calendar_admin = ($calendar->calendar_admin)? true:false;
			

			$date  = time();
			$start = strtotime("-2 months", $date);
			$end   = strtotime("+12 months", $date);

			$calendar->events = Event::getUserEventsForCalendar($authUserID, $calendar, $start, $end);
			$calendar->events = array_merge($calendar->events, Task::getUserTasksForCalendar($authUserID, $calendar->calendar_id)); 
			
			if(property_exists($calendar, 'adhoc_events') && !$calendar->adhoc_events){
				unset($calendar->adhoc_events);
			}
						
			array_push($dataRay,$calendar);
		}

		echo json_encode($dataRay);
		
	}
	
	/**
	*	CalendarCtl::Create provides an interface for createing new calendars. The
	*	properties of the new Calendar(s) are passed into the session via the Request
	*	Body in the form of JSON.
	*
	*	@return Returns newly created JSON Calendar(s).
	*
	*	CalendarCtl::create's query pattern is not atomic due to an INSERT followed
	*	by a SELECT without locking. There does exist a posibility,
	*	however unlikely, after the INSERT an update occurs before the following
	*	SELECT on the newly created Calendar(s), and if the calendar_id and
	*	last_modified where the only properties to be returned, the client's
	*	cached/local view of the Calendar(s) would be improperly bound to the wrong
	*	last_modified(s). As a result, the improperly bound last_modified(s) could
	*	lead to a false positives when performing future validations (CONDITIONAL GET),
	*	aka. lost of synchrony. To handle the lost of synchrony, CalendarCtl::create
	*	returns full Calendar(s) to the client on return.
	*/
	function create($id = null) {
		$authUserID = null;
		$authUserID = Authorize:: sharedInstance()->userID();
		if ($id !== null &&  $authUserID != $id) {
			$forbidden = new UserForbiddenException($authUserID);
			echo $forbidden->json_encode();
			exit;
		}
		$calendar = Calendar:: create($id === null? $authUserID: $id,json_decode(Request:: body()));
			$calendar->subscribed = true;
		echo json_encode($calendar);
		User:: incrementVersion($authUserID);
	}

	/**
	*	CalendarCtrl::update provides an interface for updating pre-existing calendars.
	*	The properties of the Calendar(s) to update are passed into the session via
	*	the Request Body in the form of JSON.
	*
	*
	*	@return Returns updated JSON Calendar(s) or, in the case of update conflict(s),
	*	JSON Calendar Conflict(s) of the structure:
	*		{
	*			'errno'    :409,
	*			'message'  :...,
	*			'resource' :{<calendar>},
	*			'conflicts':[{<calendar1>}...{calendarT}]}.
	*		}.
	*
	*
	*	Client sends full Calendar(s) to CalendarCtrl::update interfce. If update conflict(s) occur,
	*	CalendarCtrl::update will return the Calendar(s) in the 'conflicts' structure (above) to allow
	*	the client to settle the conflict, regardless of local client changes made to
	*	the same Calendar in the interim of request.
	*/
	function update() {
		$authUserID = Authorize:: sharedInstance()->userID();
		//$userPermissions = User:: loadPermissions($authUserID);
		$tsprops = json_decode(Request:: body());

		if (is_object($tsprops))
			$tsprops = array($tsprops);
		$ncalendars = count($tsprops);
		foreach ($tsprops as $tsprop) {
			try {
				$calendar = new Calendar($tsprop);
				$pinsqli = DistributedMySQLConnection:: writeInstance();

				//perform calendar updates
				$calendar->update_subscriptions($pinsqli);
				$calendar->update_reminders($pinsqli);
				$calendar->updateSubscription($calendar->calendar_id, $calendar->viewing, $authUserID);
				
				$hasPermission = User:: checkPermissions('modify_public_calendars',$authUserID, $tsprop->org_id);
				if($calendar->creator_id == $authUserID || $calendar->calendar_admin || $hasPermission){
					$calendar->update($pinsqli);
					if(property_exists($tsprop,'public') && $tsprop->public){
						Admin::promoteToPublicCalendar($tsprop);
						error_log(print_r('promote calendar',true));
					}
					else{
						Admin::demoteFromPublicCalendar($tsprop);
						error_log(print_r('demote calendar',true));

					}
				}

				$calendar->subscribed = false;
				$calendar->color = '';
				$colorResult = Calendar:: genericQuery("SELECT
						color, view_setting
					FROM
						calendar_subs
					WHERE
						calendar_id='{$calendar->calendar_id}'
					AND
						user_id='$authUserID'");
				if(sizeof($colorResult) > 0){
					$calendar->color = $colorResult[0]->color;
					$calendar->subscribed = true;
					$calendar->viewing = ($colorResult[0]->view_setting)? true:false;
				}
				$calendar->active = ($calendar->active)? true:false;
				$calendar->calendar_admin = ($calendar->calendar_admin)? true:false;
				$calendar->events = Event::getUserEventsForCalendar($authUserID, $calendar);
				$calendar->events = array_merge($calendar->events, Task::getUserTasksForCalendar($authUserID, $calendar->calendar_id)); 
				echo json_encode($calendar);
				
			} catch (CalendarDataConflictException $e) {
				echo $e->json_encode();
			} catch (CalendarDoesNotExist $e) {
				echo $e->json_encode();
			}
			if (--$ncalendars > 0) echo ',';
		}

		User:: incrementVersion($authUserID);
	}

	/** UPDATED FOR PINWHEEL **/
	function unsubscribe() {
		$subscription = json_decode(Request:: body());
		$authUserID = Authorize:: sharedInstance()->userID();
		$subscription->subscribed = false;

		Event::removeAdhocEvents($subscription, $authUserID);
		
		$unsubscribed = Calendar::unsubscribe($subscription, $authUserID);														
		echo json_encode($unsubscribed);
		User:: incrementVersion($authUserID);
	}
	/** UPDATED FOR PINWHEEL **/
	function subscribe($body=NULL, $return=FALSE) {
		$subscription = ($body==NULL)? json_decode(Request:: body()):$body;
		$authUserID = Authorize:: sharedInstance()->userID();
		if($subscription->color == ''){
			$subscription->color = 'blue';
		}
		if(!property_exists($subscription, 'adhoc_events')){
			$subscription->adhoc_events = false;
		}
		$subscription->subscribed = true;

		$subscribed = Calendar::subscribe($subscription, $authUserID);														
		$subscribed->events = Event::getUserEventsForCalendar($authUserID, $subscribed);
		$subscribed->events = array_merge($subscribed->events, Task::getUserTasksForCalendar($authUserID, $subscribed->calendar_id)); 
		User:: incrementVersion($authUserID);
		echo json_encode($subscribed);	
	}
	function updateVewSettings() {
		$calendar = json_decode(Request:: body());
		$authUserID = Authorize:: sharedInstance()->userID();

		Calendar::updateSubscription($calendar->calendar_id, $calendar->viewing, $authUserID);
		echo json_encode($calendar);
		User:: incrementVersion($authUserID);
	}
	function getCalendarAdmins($id) {
		$authUserID = Authorize:: sharedInstance()->userID();
		echo json_encode(Calendar::getCalendarAdmins($id));
		//error_log(print_r("get cal admins for id $id",true));
	}
	function addCalendarAdmin($calendar_id){
		$authUserID = Authorize:: sharedInstance()->userID();
		$admin = json_decode(Request:: body());
		$calendar = Calendar::load($calendar_id);

		//check to see if the admin actually exists
		if(User::validateUserName($admin->username,true)){
			$admin = User::loadWithHandle($admin->username);
		}else{
			throw new UserDoesNotExist();
		}
		$subscription->calendar_id = $calendar_id;
		$subscription->color = 'blue';
		$subscription->adhoc_events = false;
		// check the users permissions for this calendar
		if($calendar->creator_id == $authUserID || $calendar->calendar_admin){
			Calendar::addCalendarAdmin($admin, $calendar_id);
			Calendar::subscribe($subscription, $admin->user_id);		
			Calendar::sendNewAdminMessage(array($admin->email),$calendar->calendar_name);
			unset($admin->active, $admin->email, $admin->last_modified, $admin->password, $admin->settings, $admin->timezone,$admin->version);
			echo json_encode($admin);
			// send email to new admin
		}else{
			$insuficientPrivileges = new InsuficientPriviledgesException();
			echo $insuficientPrivileges->json_encode();
			exit;
		}
	}
	function deleteCalendarAdmin($calendar_id){
		$authUserID = Authorize:: sharedInstance()->userID();
		$admin = json_decode(Request:: body());
		$calendar = Calendar::load($calendar_id);
		if($calendar->creator_id == $authUserID || $calendar->calendar_admin){
			Calendar::deleteCalendarAdmin($admin, $calendar_id);
			echo json_encode($admin);
			// send email to deleted admin
		}else{
			$insuficientPrivileges = new InsuficientPriviledgesException();
			echo $insuficientPrivileges->json_encode();
			exit;
		}
	}
	/**
	*	CalendarCtrl::delete provides an interface for deleting pre-existing calendars.
	*	The properties of the Calendar(s) to delete are passed into the session via
	*	the Request Body in the form of JSON.
	*
	*	@return Returns '{}' or, in the case of delete conflicts(s), JSON Calendar
	*	Conflict(s) (see above).
	*
	*	Client sends full Calendar(s) to CalendarCtrl::delete interfce. If delete conflict(s) occur,
	*	CalendarCtrl::delete will return the Calendar(s) in the 'conflicts' structure (above) to allow
	*	the client to settle the conflict, regardless the local delte of the Same Calendar.	
	*/
	function delete(){
		$tsprops = json_decode(Request:: body());
		$authUserID = Authorize:: sharedInstance()->userID();
		echo '[';
		if (is_object($tsprops))
			$tsprops = array($tsprops);
		$ncalendars = count($tsprops);
		foreach ($tsprops as $tsprop) {
			try {
				$calendar = new Calendar($tsprop);
				$calendar->delete();
				echo json_encode($calendar);
			} catch (CalendarDataConflictException $e) {
				echo $e->json_encode();
			} catch (CalendarDoesNotExist $e) {
				echo $e->json_encode();
			}
			if (--$ncalendars > 0) echo ',';
		}
		echo ']';
		User:: incrementVersion($authUserID);
	}
}
?>

