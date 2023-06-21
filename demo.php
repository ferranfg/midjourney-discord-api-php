<?php

include 'vendor/autoload.php';

use Ferranfg\MidjourneyPhp\Midjourney;

$discord_channel_id = 'YOUR_DISCORD_CHANNEL_ID';
$discord_user_token = 'YOUR_DISCORD_USER_TOKEN';

$midjourney = new Midjourney($discord_channel_id, $discord_user_token);

// It takes about 1 minue to generate and upscale an image
$message = $midjourney->generate('A cat riding a horse --v 5');

echo $message->upscaled_photo_url;
