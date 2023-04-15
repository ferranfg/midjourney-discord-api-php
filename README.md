# Midjourney PHP Library for Discord API Image Generation

This PHP library provides a simple interface for generating images using the Midjourney Bot through the Discord API.

## Installation

You can install this library using Composer. Run the following command in your project directory:

`composer require ferranfg/midjourney-php`

## Usage

### Basic usage

To generate an image using the Midjourney Bot, you first need to create an instance of the `Midjourney` class:

```php
use Ferranfg\MidjourneyPhp\Midjourney;

$midjourney = new Midjourney($discord_channel_id, $discord_user_token);

$message = $midjourney->generate('An astronaut riding a horse');

return $message->upscaled_photo_url;
```

### Constructor

- `$discord_channel_id` - Replaces this value with the Channel ID where the Midjourney Bot is installed. You can get the Channel ID right-clicking on the channel and **Copy Channel ID**.

- `$discord_user_token` - Automatic user accounts are not allowed by Discord and can result in an account termination if found, so use it at your own risk.

    To get your user token, visit [https://discord.com/channels/@me](https://discord.com/channels/@me) and open the **Network** tab inside the **Developers Tools**. Find between your XHR requests the `Authorization` header.

    ![Discord User Token](/img/authorization.jpg)