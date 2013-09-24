'use strict';

angular.module('pinwheelApp')
  .controller('SubscriptionCtl', function ($scope, Calendar) {
		$scope.toggle = function(name){
			$scope[name] = !$scope[name];
		}

		//open calendar add/subscribe drawer
		$scope.add = function() {
			$scope.newCalendar = new Calendar();
			$scope.toggle('addingSubscription');
		}

		//save a newly created calendar
		$scope.save = function() {
			$scope.newCalendar.recent = true;
			$scope.newCalendar.viewing = true;
			$scope.newCalendar.$save({}, function(calendar) {
				console.log(calendar);
				$scope.calendars.push(calendar);
				$scope.newCalendar = new Calendar();
			});
		}
  });