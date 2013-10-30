'use strict';

angular.module('pinwheelApp', ['ngResource', 'ui.date', 'ngRoute', 'ngAnimate'])
	.config(function ($routeProvider) {
		$routeProvider
			.when('/calendar/:year/:month/:day', {
				templateUrl: 'modules/calendar/_view_calendar.html',
				controller: 'CalendarCtl',
				reloadOnSearch: false
			})
			.when("/handbook", {
				templateUrl: 'modules/handbook/main.html',
				controller: 'HandbookCtl'
			})
			.when("/messaging", {
				templateUrl: 'modules/messaging/main.html',
				controller: 'MessagingCtl'
			})
			.when("/reference", {
				templateUrl: 'modules/reference/main.html',
				controller: 'ReferenceCtl'
			})
			.when("/login", {
				templateUrl: 'modules/login/login.html',
				controller: 'LoginCtl'
			})
			.when("/new_user", {
				templateUrl: 'modules/new_user/new_user.html',
				controller: 'NewUserCtl'
			})
			.when("/forgot_password", {
				templateUrl: 'modules/forgot_password/forgot_password.html',
				controller: 'ForgotPasswordCtl'
			})
			.when("/reset_password/:reset_token", {
				templateUrl: 'modules/reset_password/reset_password.html',
				controller: 'ResetPasswordCtl'
			})
			.when("/activate_contact_point/:activation_token", {
				templateUrl: 'modules/activate_contact_point/activate_contact_point.html',
				controller: 'ActivateContactPointCtl'
			})
			.otherwise({
				redirectTo: '/calendar/'+(new Date().getFullYear())+'/'+(new Date().getMonth()+1)+'/'+(new Date().getDate())
			});
	})
	.value("localStorage", localStorage)
	.value("Debounce", function(func, threshold, execAsap) {
		var timeout;
	    return function debounced () {
	        var obj = this, args = arguments;
	        function delayed () {
	            if (!execAsap)
	                func.apply(obj, args);
	            timeout = null; 
	        };
	 
	        if (timeout)
	            clearTimeout(timeout);
	        else if (execAsap)
	            func.apply(obj, args);
	 
	        timeout = setTimeout(delayed, threshold || 100); 
	    };
	})
	.factory('Auth', function($resource){
		return $resource('/api/v1/auth/token/:user/:pass');
	})
	.factory('User', function($resource){
		return $resource('/api/v1/user/:id', {}, {update: {method:'PUT'}});
	})
	.factory('NewUser', function($resource){
		return $resource('/api/v1/user/new/', {}, {post: {method:'POST'}});
	})
	.factory('Task', function($resource){
		return $resource('/api/v1/task/:id/:version', {},
			{
				save: {
					method:'POST',
					isArray: false,
					transformRequest: function(data){
						if(data.hasDueDate && data.due_time){
							data.due_time = new Date(data.due_time).getTime()/1000;
							data.hasDueDate = true;
						}
						return angular.toJson(data);
					},
					transformResponse: function(data){
						data = angular.fromJson(data);
						if(parseInt(data.due_time) && !data.hasDueDate){
							data.due_time = new Date(data.due_time*1000);
							data.hasDueDate = true;
						}else{
							delete data.due_time;
						}
						return data;
					}
				},
				update: {
					method:'PUT',
					isArray: false,
					transformRequest: function(data){
						data = angular.fromJson(data);
						if(data.hasDueDate){
							data.due_time = new Date(data.due_time).getTime()/1000;
							data.hasDueDate = true;
						}else{
							delete data.due_time
							delete data.hasDueDate
						}
						return angular.toJson(data);
					},
					transformResponse: function(data){
						var data = angular.fromJson(data);
						if(data.due_time && parseInt(data.due_time)){
							data.due_time = new Date(data.due_time*1000);
							data.hasDueDate = true;
						}else{
							delete data.due_time
							delete data.hasDueDate
						}
						return data;
					}
				},
				delete: {method: 'DELETE', params: {version: ':version'}},
				query: {
					method: 'GET',
					isArray: true,
					transformRequest: function(data){
						return data;
					},
					transformResponse: function(data){
						var tasks = angular.fromJson(data);
						angular.forEach(tasks, function(task,k){
							if(parseInt(task.due_time)){
								tasks[k].due_time = new Date(task.due_time*1000);
							}else{
								delete task.due_time
							}
						});
						return tasks;
					}
				},
				get: {method: 'GET'}
			});
	})
	.factory('Event', function($resource){
		return $resource('/api/v1/event/:id/:year/:month/:day/:version', {}, {
			save: {
				method:'POST',
				isArray: false,
				transformRequest: function(data){
					// TODO: add if statements in case task does not have due time
					// TODO: for save and update
					data.event_start = new Date(data.event_start).getTime()/1000;
					data.event_end = new Date(data.event_end).getTime()/1000;
					return angular.toJson(data);
				},
				transformResponse: function(data){
					data = angular.fromJson(data);
					data.event_start = new Date(data.event_start*1000);
					data.event_end = new Date(data.event_end*1000);
					return data;
				}
			},
			update: {
				method:'PUT',
				isArray: false,
				transformRequest: function(data){
					data = angular.fromJson(data);
					data.event_start = new Date(data.event_start).getTime()/1000;
					data.event_end = new Date(data.event_end).getTime()/1000;
					return angular.toJson(data);
				},
				transformResponse: function(data){
					var data = angular.fromJson(data);
					data.event_start = new Date(data.event_start*1000);
					data.event_end = new Date(data.event_end*1000);
					return data;
				}
			},
			query: {
					method: 'GET',
					isArray: true,
					transformResponse: function(data){
						var events = angular.fromJson(data);
						angular.forEach(events, function(event,k){
							events[k].event_start = new Date(parseInt(event.event_start*1000));
							events[k].event_end = new Date(parseInt(event.event_end*1000));
						});
						return events;
					}
				},
		});
	})
	.factory('Reminder', function($resource){
		return $resource('/api/v1/reminder/:id', {}, {update: {method:'PUT'}});
	})
	.factory('Calendar', function($resource){
		return $resource('/api/v1/calendar/:id/:version', {}, {update: {method:'PUT'}, delete: {method: 'DELETE', params: {version: ':version'}}});
	})
	.factory('Timezones', function($resource){
		return $resource('/timezone.json',{},{
			query: {
					method: 'GET',
					isArray: true
			}})
	})
	.config(['$httpProvider', function ($httpProvider) {
		$httpProvider.defaults.headers.common['Authorization'] =  localStorage['token'];
	}]);
