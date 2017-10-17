<?php
namespace Giglio\Mailchimp\Models;

use Illuminate\Database\Eloquent\Model;

class MailchimpLogModel extends Model
{
    protected $table = 'campaign_log';
    protected $fillable = ['user_id', 'deal_id'];
}