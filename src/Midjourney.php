<?php

namespace Ferranfg\MidjourneyPhp;

use Exception;
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
            if (str_starts_with($item->{$key}, $value)) return $item;
        }

        return null;
    }

    public function imagine(string $prompt)
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

        sleep(8);

        $imagine_message = null;

        while (is_null($imagine_message))
        {
            $imagine_message = $this->getImagine($prompt);

            if (is_null($imagine_message)) sleep(8);
        }

        return $imagine_message;
    }

    public function getImagine(string $prompt)
    {
        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        $raw_message = self::firstWhere($response, 'content', "**{$prompt}** - <@" . self::$user_id . '>');

        if (is_null($raw_message)) return null;

        return (object) [
            'id' => $raw_message->id,
            'prompt' => $prompt,
            'raw_message' => $raw_message
        ];
    }

    public function upscale($message, int $upscale_index = 0)
    {
        if ( ! property_exists($message, 'raw_message'))
        {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 or $upscale_index > 3)
        {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        $upscale_hash = null;
        $raw_message = $message->raw_message;

        if (property_exists($raw_message, 'components') and is_array($raw_message->components))
        {
            $upscales = $raw_message->components[0]->components;

            $upscale_hash = $upscales[$upscale_index]->custom_id;
        }

        $params = [
            'type' => 3,
            'guild_id' => self::$guild_id,
            'channel_id' => self::$channel_id,
            'message_flags' => 0,
            'message_id' => $message->id,
            'application_id' => self::APPLICATION_ID,
            'session_id' => self::SESSION_ID,
            'data' => [
                'component_type' => 2,
                'custom_id' => $upscale_hash
            ]
        ];

        self::$client->post('interactions', [
            'json' => $params
        ]);

        $upscaled_photo_url = null;

        while (is_null($upscaled_photo_url))
        {
            $upscaled_photo_url = $this->getUpscale($message, $upscale_index);

            if (is_null($upscaled_photo_url)) sleep(3);
        }

        return $upscaled_photo_url;
    }

    public function getUpscale($message, $upscale_index = 0)
    {
        if ( ! property_exists($message, 'raw_message'))
        {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 or $upscale_index > 3)
        {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        $prompt = $message->prompt;

        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        $message_index = $upscale_index + 1;
        $message = self::firstWhere($response, 'content', "**{$prompt}** - Image #{$message_index} <@" . self::$user_id . '>');

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
        $imagine = $this->imagine($prompt);

        $upscaled_photo_url = $this->upscale($imagine, $upscale_index);

        return (object) [
            'imagine_message_id' => $imagine->id,
            'upscaled_photo_url' => $upscaled_photo_url
        ];
    }
}