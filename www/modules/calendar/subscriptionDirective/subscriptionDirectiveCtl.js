'use strict';

angular.module('pinwheelApp')
	.controller('SubscriptionDirectiveCtl', function ($scope, $routeParams) {
		if ($scope.watcher != undefined) {
			$scope.watcher[$scope.calendar.calendar_id] = {viewing: $scope.calendar.viewing, color: $scope.calendar.color};
		}

		//open existing calendar for editing
		$scope.edit = function() {
			$scope.editCalendar || ($scope.editCalendar = {});
			angular.copy($scope.calendar, $scope.editCalendar);
			$scope.editingCalendar = true;
		}

		//update existing calendar
		$scope.update = function() {
			angular.copy($scope.editCalendar, $scope.calendar);
			$scope.calendar.$update({id: $scope.calendar.calendar_id}, function(calendar) {
				$scope.calendar = calendar;
				$scope.watcher[$scope.calendar.calendar_id].color = $scope.calendar.color; 
				$scope.cancel();
			});
		}

		//subscribe to a calendar
		$scope.subscribe = function() {
			$scope.calendar.$update({id: "subscribe"}, function(calendar) {
				$scope.calendar = calendar;
				$scope.calendar.recent = true;
				$scope.calendar.viewing = true;
			});
		}	

		//unsubscribe from a calendar
		$scope.unsubscribe = function() {
			$scope.calendar.$update({id: "unsubscribe"}, function(calendar) {
				$scope.calendar = calendar;
				$scope.calendar.recent = false;
				$scope.calendar.viewing = false;
				$scope.cancel();
			});
		}

		//cancel editing of calendar
		$scope.cancel = function() {
			$scope.editingCalendar = false;
		}

		//set if a calendar's events and tasks are visible
		$scope.setShowState = function() {
			$scope.calendar.viewing = $scope.watcher[$scope.calendar.calendar_id].viewing;
			$scope.calendar.$update({id: $scope.calendar.calendar_id}, function(calendar) {
				$scope.calendar = calendar;
			});
		}
});
