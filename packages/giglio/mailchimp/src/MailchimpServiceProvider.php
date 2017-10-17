<?php

namespace Giglio\Mailchimp;
use Illuminate\Foundation\AliasLoader;

use Illuminate\Support\ServiceProvider;

class MailchimpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        include __DIR__.'/routes.php';
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        // register our controller
        $this->app->make('Giglio\Mailchimp\MailchimpController');

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('MailchimpCampaignModel','Giglio\Mailchimp\Models\MailchimpCampaignModel');
        $loader->alias('MailchimpCampaignLanguageModel','Giglio\Mailchimp\Models\MailchimpCampaignLanguageModel');
        $loader->alias('MailchimpLanguageModel','Giglio\Mailchimp\Models\MailchimpLanguageModel');
        $loader->alias('MailchimpLogModel','Giglio\Mailchimp\Models\MailchimpLogModel');
    }
}
