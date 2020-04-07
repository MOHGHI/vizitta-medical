<?php

Route::group(['middleware' => ['CheckPassword', 'ChangeLanguage', 'api']], function () {

    Route::group(['prefix' => 'services'], function () {
        Route::post('/get-clinic-service-available-times', 'GlobalVisitsController@getClinicServiceAvailableTimes');
        Route::post('/reserve-home-clinic-service', 'GlobalVisitsController@reserveHomeClinicService');
    });

});

