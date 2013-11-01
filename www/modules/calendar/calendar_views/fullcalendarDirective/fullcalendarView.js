'use strict';

angular.module('pinwheelApp')
	.directive('fullcalendarView', function(){
		return {
			restrict: 'E',
			templateUrl: 'modules/calendar/calendar_views/fullcalendarDirective/_fullcalendar_view.html',
			controller: function($scope, $filter, $element, $attrs, $routeParams){
				$scope.thisMonthsEvents = function(item) {
				 	var startOfMonth = new Date($routeParams.month+'-01-'+$routeParams.year).getTime()/1000;
				 	var endOfMonth = new Date($routeParams.month*1+1+'-01-'+$routeParams.year).getTime()/1000;
				 	var itemStart = new Date((item.event_start||item.due_time)).getTime()/1000;
					return(itemStart >= startOfMonth && itemStart < endOfMonth);
				}
				$scope.mainAreaHeight = function(){
					var windowHeight = $(window).height();
					var mainHeaderHeight = $(".top-bar").outerHeight();
					var contentHeadHeight = $("#content-header").outerHeight();
					//var fcHeaderHeight = $(".fc-header").outerHeight();
					var height = windowHeight - mainHeaderHeight - contentHeadHeight;
					//console.warn([windowHeight,mainHeaderHeight,contentHeadHeight,fcHeaderHeight]);
					return height;
				}

				$scope.eventSources = function(){
					/*ret = array()
					angular.forEach(each calendar){
						ret.push($filter($scope.events)(calendar_id))
						$filter('date')(modelValue, formDatetimeFormat);
					}
					return ret*/
					return [$scope.events, $scope.tasks]
				}

				$scope.calendarOptions = {
					editable: true,
					header:{
						left:null,
						center:null,
						right:null
					},
					month:$routeParams.month -1,
					year:$routeParams.year,
					date:$routeParams.day,
					weekMode:'liquid',
					height:$scope.mainAreaHeight(),
					viewRender: function(view,element) {
       					 console.warn('fullCalendar render');
    				}
				};
			}
		}
	});

