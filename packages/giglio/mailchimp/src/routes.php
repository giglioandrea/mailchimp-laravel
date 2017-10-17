<?php

Route::get('UpdateLanguage/{list}', 'Giglio\Mailchimp\MailchimpController@UpdateLanguage');
Route::get('CreateCampaign/{id}', 'Giglio\Mailchimp\MailchimpController@CreateCampaign');
Route::get('ReplicateCampaign/{id}', 'Giglio\Mailchimp\MailchimpController@ReplicateCampaign');