<?php

include 'vendor/autoload.php';

use Ferranfg\MidjourneyPhp\Midjourney;

$discord_channel_id = '';
$discord_user_token = '';

$midjourney = new Midjourney($discord_channel_id, $discord_user_token);

// It takes about 1 minue to generate and upscale an image
$message = $midjourney->generate('A cat riding a horse --v 5');

echo $message->upscaled_photo_url;