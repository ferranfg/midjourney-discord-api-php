<?php

namespace Ferranfg\MidjourneyPhp;

use Exception;
use GuzzleHttp\Client;

/**
 * Class allowing to create an image from a prompt and to retrieve the link of the image
 * 
 * Documentation Discord API : https://discord.com/developers/docs/interactions/application-commands
 */
class Midjourney
{

    private $apiUrl = 'https://discord.com/api/v10';    // Discord API URL
    private $applicationId  = '936929561302675456';     // Unique ID for the application

    private $dataId;            // Unique ID for the command
    private $dataVersion;       // Unique Version for the command

    private $sessionId;         // Unique ID for the session
    private $client;            // GuzzleHttp\Client
    private $channel_id;        // Discord Channel ID
    private $oauth_token;       // Discord OAuth Token User
    private $guild_id;          // Discord Guild ID
    private $user_id;           // Discord User ID

    private $uniqueId;          // Unique ID for the prompt


    /**
     * Constructor
     *
     * @param string $channel_id
     * @param string $oauth_token
     */
    public function __construct($channel_id, $oauth_token)
    {
        $this->sessionId = md5(uniqid());

        $this->channel_id = $channel_id;
        $this->oauth_token = $oauth_token;

        // Création du client GuzzleHttp
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Authorization' => $this->oauth_token
            ]
        ]);

        // Guild_id recovery
        $response = $this->client->get('channels/' . $this->channel_id);
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        $this->guild_id = $json['guild_id'] ?? null;

        // User_id recovery
        $response = $this->client->get('users/@me');
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        $this->user_id = $json['id'];

        // Retrieval of dataId and dataVersion
        $response = $this->client->get('applications/' . $this->applicationId . '/commands');
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        $this->dataId       = $json[0]['id'];
        $this->dataVersion  = $json[0]['version'];
    }


    /**
     * Global method to recover an image
     *
     * @param   string      $prompt             Midjourney prompt
     * @param   integer     $upscale_index      Choice of image to upscale - default: random 0.3
     * @return  object
     */
    public function generate($prompt, $upscale_index = null)
    {
        // Random image selection if $upscale_index is null
        if (is_null($upscale_index)) $upscale_index = rand(0, 3);

        $imagine = $this->imagine($prompt);
        $upscaled_photo_url = $this->upscale($imagine, $upscale_index);

        return (object) [
            'imagine_message_id' => $imagine['id'],
            'upscaled_photo_url' => $upscaled_photo_url
        ];
    }


    /**
     * Call /imagine
     *
     * @param   string      $prompt     Prompt midjourney
     * @return  void
     */
    public function imagine(string $prompt)
    {
        $this->uniqueId = time() - rand(0, 1000);
        $promptWithId = $prompt . ' --seed ' . $this->uniqueId;

        $params = [
            'type'              => 2,
            'application_id'    => $this->applicationId,
            'guild_id'          => $this->guild_id,
            'channel_id'        => $this->channel_id,
            'session_id'        => $this->sessionId,
            'data' => [
                'id'        => $this->dataId,
                'version'   => $this->dataVersion,
                'name'      => 'imagine',
                'type'      => 1,
                'options'   => [
                    [
                        'type'  => 3,
                        'name'  => 'prompt',
                        'value' => $promptWithId
                    ],
                ],
            ],
        ];

        if (is_null($this->guild_id)) {
            unset($params['guild_id']);
        }

        $this->client->post('interactions', [
            'json' => $params
        ]);

        sleep(8);

        $imagine_message = null;

        // Max time loop: just over 5 minutes of waiting
        $maxLoop = 40;

        while (is_null($imagine_message)) {
            $maxLoop--;
            if ($maxLoop == 0) break;

            $imagine_message = $this->getImagine();
            if (is_null($imagine_message)) sleep(8);
        }

        return $imagine_message;
    }


    /**
     * Method to retrieve the Midjourney message and identify when the 4 visuals are ready
     * 
     * @return void
     */
    public function getImagine()
    {
        $response = $this->client->get('channels/' . $this->channel_id . '/messages');
        $response = $response->getBody()->getContents();
        $items = json_decode($response, true);

        $raw_message = null;

        foreach ($items as $item) {
            if (
                str_contains($item['content'], $this->uniqueId) &&
                str_contains($item['content'], '<@' . $this->user_id . '> (fast)')
            ) {
                $raw_message = $item;
                break;
            }

            if (is_null($raw_message)) {
                if (
                    str_contains($item['content'], $this->uniqueId) &&
                    str_contains($item['content'], '<@' . $this->user_id . '> (Open on website for full quality) (fast)')
                ) {
                    $raw_message = $item;
                    break;
                }
            }
        }

        if (is_null($raw_message)) return null;

        return [
            'id'            => $raw_message['id'],
            'raw_message'   => $raw_message
        ];
    }


    /**
     * Method to upscale an image of Midjourney among the 4 proposed
     * 
     * @param   array   $message            Array returned by the getImagine method
     * @param   integer $upscale_index      Choice of image to upscale (0.3)
     * @return  void
     */
    public function upscale($message, int $upscale_index)
    {
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 or $upscale_index > 3) {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        $upscale_hash = null;
        $raw_message = $message['raw_message'];

        if (isset($raw_message['components']) && is_array($raw_message['components'])) {
            $upscales = $raw_message['components'][0]['components'];
            $upscale_hash = $upscales[$upscale_index]['custom_id'];
        }

        $params = [
            'type'              => 3,
            'guild_id'          => $this->guild_id,
            'channel_id'        => $this->channel_id,
            'message_flags'     => 0,
            'message_id'        => $message['id'],
            'application_id'    => $this->applicationId,
            'session_id'        => $this->sessionId,
            'data' => [
                'component_type' => 2,
                'custom_id'     => $upscale_hash
            ]
        ];

        if (is_null($this->guild_id)) {
            unset($params['guild_id']);
        }

        $this->client->post('interactions', [
            'json' => $params
        ]);

        $upscaled_photo_url = null;

        // Max time loop: 3 minutes
        $maxLoop = 60;

        while (is_null($upscaled_photo_url)) {
            $maxLoop--;
            if ($maxLoop == 0) break;

            $upscaled_photo_url = $this->getUpscale($message, $upscale_index);
            if (is_null($upscaled_photo_url)) sleep(3);
        }

        return $upscaled_photo_url;
    }


    /**
     * Method to check if the upscaled image is ready
     * 
     * @param   array   $message            Array returned by the getImagine method
     * @param   integer $upscale_index      Choice of image to upscale (0.3)
     * @return  void
     */
    public function getUpscale($message, $upscale_index = 0)
    {
        if (!isset($message['raw_message'])) {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 || $upscale_index > 3) {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        $response = $this->client->get('channels/' . $this->channel_id . '/messages');
        $response = $response->getBody()->getContents();
        $items = json_decode($response, true);

        $message_index = $upscale_index + 1;
        $message = null;

        foreach ($items as $item) {
            if (
                str_contains($item['content'], $this->uniqueId) &&
                str_contains($item['content'], "Image #{$message_index} <@{$this->user_id}>")
            ) {
                $message = $item;
                break;
            }

            if (is_null($message)) {
                if (
                    str_contains($item['content'], $this->uniqueId) &&
                    str_contains($item['content'], "Upscaled by <@{$this->user_id}> (fast)")
                ) {
                    $message = $item;
                    break;
                }
            }
        }

        return (!is_null($message) && isset($message['attachments'])) ? $message['attachments'][0]['url'] : null;
    }
}
