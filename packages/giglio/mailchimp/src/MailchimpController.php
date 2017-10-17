<?php

namespace Giglio\Mailchimp;

use \DrewM\MailChimp\MailChimp;
use Giglio\Mailchimp\Models\MailchimpCampaignLanguageModel;
use Giglio\Mailchimp\Models\MailchimpCampaignModel;
use Giglio\Mailchimp\Models\MailchimpLanguageModel;
use Giglio\Mailchimp\Models\MailchimpLogModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
require(__DIR__ . '/../vendor/autoload.php');
class MailchimpController extends Controller
{
    static $config = null;
    public function __construct()
    {
        $config = $this->Config();
        $this->MailChimp = new MailChimp($config['apikey']);
    }

    static function Config()
    {
        if (self::$config == null) {
            $json = file_get_contents(__DIR__ . "/config.json");
            self::$config = json_decode($json, true);
        }
        return self::$config;
    }

    public function GetLists()
    {
        $result = $this->MailChimp->get('lists');
        $this->readResponse($result);
    }

    /**
     * @param $list
     */
    public function UpdateLanguage($list)
    {
        $result = $this->MailChimp->get('lists/' . $list . '/segments');
        foreach ($result['segments'] as $segment) {
            if ($segment['options']['conditions'][0]['field'] == 'language') {
                $count = \Giglio\Mailchimp\Models\MailchimpLanguageModel::where('segment_id', $segment['id'])->count();
                if (count($count) == 0) {
                    $language = new \Giglio\Mailchimp\Models\MailchimpLanguageModel;

                    $language->segment_id = trim($segment['id']);
                    $language->label = $segment['name'];

                    $language->save();
                }
            }
        }
    }

    # MC call

    /**
     * @param $data
     * @return mixed
     */
    public function Create($data)
    {
        $result = $this->MailChimp->post('campaigns', $data);
        if (isset($result['id'])) {
            return $result['id'];
        } else {
            $this->readResponse($result);
        }
    }

    /**
     * @param $campaign_id
     * @return mixed
     */
    public function Replicate($campaign_id)
    {
        $result = $this->MailChimp->post('campaigns/' . $campaign_id . '/actions/replicate');
        if (isset($result['id'])) {
            return $result['id'];
        } else {
            $this->readResponse($result);
        }
    }

    /**
     * @param $campaign_id
     * @param $data
     * @return mixed
     */
    public function Edit($campaign_id, $data)
    {
        $result = $this->MailChimp->patch('campaigns/' . $campaign_id, $data);
        if (isset($result['id'])) {
            return $result['id'];
        } else {
            $this->readResponse($result);
        }
    }

    /**
     * @param $campaign_id
     * @param $data
     * @return mixed
     */
    public function EditContent($campaign_id, $data)
    {
        $result = $this->MailChimp->put('campaigns/' . $campaign_id . "/content", $data);
        if (isset($result['plain_text'])) {
            return $result['plain_text'];
        } else {
            $this->readResponse($result);
        }
    }

    # App Call

    /**
     * @param $id
     */
    public function CreateCampaign($id)
    {
        $config = $this->Config();
        $C = MailchimpCampaignModel::where('id', $id)->get()->first();
        $CLIST = MailchimpCampaignLanguageModel::where('campaign_id', $id)->where('reply_of', '0')->where('campaign_mc_id', '')->get();
        foreach ($CLIST as $row) {
            $L = MailchimpLanguageModel::where('id', $row['language_id'])->get()->first();
            $param = [
                'type' => 'regular',
                'recipients' => [
                    'list_id' => $C->list_id,
                    'segment_opts' => [
                        'saved_segment_id' => intval(($row['segment_id'] != '0') ?: $L['segment_id'])
                    ]
                ],
                'settings' => [
                    'subject_line' => $row['subject'],
                    'title' => $C->label . " " . $L->label,
                    'from_name' => $config['from_name'],
                    'reply_to' => $config['reply_to'],
                    'to_name' => '*|FNAME|* *|LNAME|*',
                    'template_id' => intval(($row['template_id'] != '0') ?: $L['template_id']),
                ],
                'tracking' => [
                    'opens' => true,
                    'html_clicks' => true,
                    'text_clicks' => false,
                    'goal_tracking' => false,
                    'ecomm360' => true,
                    'google_analytics' => $L['slug'] . '_' . date('d_m_y') . '_' . $C->ga_tags,
                ]
            ];
            $this->Log(['action' => 'newcampaign', 'campaign_id' => $id, 'campaign_language_id' => $row['id']]);
            $campaign_id = $this->Create($param);
            if (isset($campaign_id)) {
                MailchimpCampaignLanguageModel::where('id', $row['id'])
                    ->update(['campaign_mc_id' => trim($campaign_id)]);
                echo "Nuova campagna: " . $campaign_id ."<br />";
            }
        }
    }

    /**
     * @param $data
     */
    public function ReplicateCampaign($id)
    {
        # config
        $config = $this->Config();
        $C = MailchimpCampaignModel::where('id', $id)->get()->first();
        $CLIST = MailchimpCampaignLanguageModel::where('campaign_id', $id)->where('reply_of', '!=', '0')->get();
        foreach ($CLIST as $row) {

            $L = MailchimpLanguageModel::where('id', $row['language_id'])->get()->first();
            $CL = MailchimpCampaignLanguageModel::where('id', $row['reply_of'])->get()->first();
            $param = [
                'type' => 'regular',
                'recipients' => [
                    'list_id' => $C['list_id'],
                    'segment_opts' => [
                        'saved_segment_id' => intval(($row['segment_id'] != '0') ?: $L['segment_id'])
                    ]
                ],
                'settings' => [
                    'subject_line' => $row['subject'],
                    'title' => $C['label'] . " " . $L['label'] . " Not open",
                    'from_name' => $config['from_name'],
                    'reply_to' => $config['reply_to'],
                    'to_name' => '*|FNAME|* *|LNAME|*',
                    'template_id' => intval(($row['template_id'] != '0') ?: $L['template_id']),
                ],
                'tracking' => [
                    'opens' => true,
                    'html_clicks' => true,
                    'text_clicks' => false,
                    'goal_tracking' => false,
                    'ecomm360' => true,
                    'google_analytics' => $L['slug'] . '_' . date('d_m_y') . '_' . $C['ga_tags'] . "_notopen",
                ]
            ];
            $this->Log(['action' => 'replicate', 'campaign_id' => $id, 'campaign_language_id' => $row['id']]);
            $campaign_id = $this->Replicate($CL['campaign_mc_id']);
            if (isset($campaign_id)) {
                MailchimpCampaignLanguageModel::where('id', $row['id'])
                    ->update(['campaign_mc_replicate_id' => trim($campaign_id)]);
                echo "Nuova campagna replicata: " . $campaign_id . "<br />";
                $this->Edit($campaign_id, $param);
            }
        }
    }

    /**
     * @param $data
     */
    public function EditCampaign($data)
    {
        # Estraggo le campagne replicate
        $CLIST = DB::Query("SELECT * FROM `campaign_language` WHERE campaign_id = :campaign_id AND reply_of != :reply_of", ['campaign_id' => $data['id'], 'reply_of' => '0']);
        if (count($CLIST) == 0) {
            # Altrimenti estraggo le campagne orginali
            $CLIST = DB::Query("SELECT * FROM `campaign_language` WHERE campaign_id = :campaign_id AND reply_of = :reply_of", ['campaign_id' => $data['id'], 'reply_of' => '0']);
        }
        foreach ($CLIST as $row) {
            $L = DB::One("SELECT * FROM `language` WHERE id = :id", ['id' => $row['language_id']]);
            $template = ($row['template_id'] != '0') ? $row['template_id']:  $L['template_id'];
            $template = ($row['html']) ? 0 : $template;

            $param = [
                'settings' => [
                    'subject_line' => $row['subject'],
                    'template_id' => intval($template),
                ]
            ];
            $this->Log(['action' => 'edit', 'campaign_id' => $data['id'], 'campaign_language_id' => $row['id']]);
            $campaign_id = $this->Edit($row['campaign_mc_id'], $param);
            if (isset($campaign_id)) {
                echo "Campagna modificata: " . $campaign_id . PHP_EOL;
            }
            if (isset($row['plain_text']) || isset($row['html'])) {
                $this->Log([
                    'action' => 'edit_content',
                    'campaign_id' => $data['id'],
                    'campaign_language_id' => $row['id']
                ]);
                $text = $this->EditContent($row['campaign_mc_id'], ['plain_text' => $row['plain_text'], 'html' => $row['html']]);
                if (isset($text)) {
                    echo "Contenuto modificato" .  PHP_EOL;
                }
            }
        }
    }

    /**
     * @param $result
     */
    public function readResponse($result)
    {
        if (isset($result['errors'])) {
            echo "Errore: status " . $result['status'] . " - " . $result['detail'];

            $listerrors = '';
            foreach ($result['errors'] as $error) {
                $listerrors .= $error['field'] . " > " . $error['message'] . "\n";
            }
            echo PHP_EOL . $listerrors;
        } else {
            echo "<pre>";
            var_dump($result);
            echo "</pre>";
        }
    }

    /**
     * @param $data
     */
    public function Log($data)
    {
        $log = new MailchimpLogModel();

        $log->campaign_id = $data['campaign_id'];
        $log->campaign_language_id = $data['campaign_language_id'];
        $log->action = $data['action'];
        $log->date = date("Y-m-d H:i:s");

        $log->save();
    }

}
