<?php

namespace Ferranfg\MidjourneyPhp;

use GuzzleHttp\Client;

class Midjourney {

    private const API_URL = 'https://discord.com/api/v9';

    private const APPLICATION_ID = '936929561302675456';

    private const DATA_ID = '938956540159881230';

    private const DATA_VERSION = '1077969938624553050';

    private const SESSION_ID = '2fb980f65e5c9a77c96ca01f2c242cf6';

    private static $client;

    private static $channel_id;

    private static $oauth_token;

    private static $guild_id;

    private static $user_id;

    public function __construct($channel_id, $oauth_token)
    {
        self::$channel_id = $channel_id;
        self::$oauth_token = $oauth_token;

        self::$client = new Client([
            'base_uri' => self::API_URL,
            'headers' => [
                'Authorization' => self::$oauth_token
            ]
        ]);

        $request = self::$client->get('channels/' . self::$channel_id);
        $response = json_decode((string) $request->getBody());

        self::$guild_id = $response->guild_id;

        $request = self::$client->get('users/@me');
        $response = json_decode((string) $request->getBody());

        self::$user_id = $response->id;
    }

    private static function firstWhere($array, $key, $value)
    {
        foreach ($array as $item)
        {
            if ($item->{$key} == $value) return $item;
        }

        return null;
    }

    public static function imagine($prompt)
    {
        $params = [
            'type' => 2,
            'application_id' => self::APPLICATION_ID,
            'guild_id' => self::$guild_id,
            'channel_id' => self::$channel_id,
            'session_id' => self::SESSION_ID,
            'data' => [
                'version' => self::DATA_VERSION,
                'id' => self::DATA_ID,
                'name' => 'imagine',
                'type' => 1,
                'options' => [[
                    'type' => 3,
                    'name' => 'prompt',
                    'value' => $prompt
                ]],
                'application_command' => [
                    'id' => self::DATA_ID,
                    'application_id' => self::APPLICATION_ID,
                    'version' => self::DATA_VERSION,
                    'default_permission' => true,
                    'default_member_permissions' => '',
                    'type' => 1,
                    'nsfw' => false,
                    'name' => 'imagine',
                    'description' => 'Create images with Midjourney',
                    'dm_permission' => true,
                    'options' => [[
                        'type' => 3,
                        'name' => 'prompt',
                        'description' => 'The prompt to imagine',
                        'required' => true
                    ]]
                ],
                'attachments' => []
            ]
        ];

        self::$client->post('interactions', [
            'json' => $params
        ]);
    }

    public static function getImagine($prompt)
    {
        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        $message = self::firstWhere($response, 'content', "**{$prompt}** - <@" . self::$user_id . '> (fast)');

        if (is_null($message)) return [null, null];

        $imagine_message_id = $message->id;
        $upscale_job_hash = null;

        if (property_exists($message, 'components') and is_array($message->components))
        {
            $upscales = $message->components[0]->components;

            $upscale_job_hash = $upscales[0]->custom_id;
        }

        return [$imagine_message_id, $upscale_job_hash];
    }

    public static function upscale($message_id, $message_hash)
    {
        $params = [
            'type' => 3,
            'guild_id' => self::$guild_id,
            'channel_id' => self::$channel_id,
            'message_flags' => 0,
            'message_id' => $message_id,
            'application_id' => self::APPLICATION_ID,
            'session_id' => self::SESSION_ID,
            'data' => [
                'component_type' => 2,
                'custom_id' => $message_hash
            ]
        ];

        self::$client->post('interactions', [
            'json' => $params
        ]);
    }

    public static function getUpscale($prompt)
    {
        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        $message = self::firstWhere($response, 'content', "**{$prompt}** - Image #1 <@" . self::$user_id . '>');

        if (is_null($message))
        {
            $message = self::firstWhere($response, 'content', "**{$prompt}** - Upscaled by <@" . self::$user_id . '> (fast)');
        }

        if (is_null($message)) return null;

        if (property_exists($message, 'attachments') and is_array($message->attachments))
        {
            $attachment = $message->attachments[0];

            return $attachment->url;
        }

        return null;
    }

    public function generate($prompt, $upscale_index = 0)
    {
        self::imagine($prompt);

        $imagine_message_id = null;
        $upscale_job_hash = null;

        while (is_null($imagine_message_id))
        {
            list($imagine_message_id, $upscale_job_hash) = self::getImagine($prompt);

            if (is_null($imagine_message_id)) sleep(8);
        }

        self::upscale($imagine_message_id, $upscale_job_hash);

        $upscaled_photo_url = null;

        while (is_null($upscaled_photo_url))
        {
            $upscaled_photo_url = self::getUpscale($prompt, $upscale_index);

            if (is_null($upscaled_photo_url)) sleep(3);
        }

        return (object) [
            'imagine_message_id' => $imagine_message_id,
            'upscaled_photo_url' => $upscaled_photo_url
        ];
    }
}