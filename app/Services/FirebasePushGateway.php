<?php

namespace App\Services;

use App\Contracts\PushGateway;
use App\Exceptions\InvalidPushTokenException;
use App\Exceptions\PermanentPushException;
use App\Exceptions\TransientPushException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FirebasePushGateway implements PushGateway
{
    public function __construct(private Messaging $messaging) {}

    public function send(string $token, string $title, string $body, array $data): void
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data);

        try {
            $this->messaging->send($message);
        } catch (NotFound $exception) {
            throw new InvalidPushTokenException('FCM rejected the device token.', 0, $exception);
        } catch (QuotaExceeded|ServerError|ServerUnavailable $exception) {
            throw new TransientPushException('FCM is temporarily unavailable.', 0, $exception);
        } catch (InvalidMessage $exception) {
            throw new PermanentPushException('FCM rejected the message.', 0, $exception);
        } catch (MessagingException $exception) {
            throw new TransientPushException('FCM delivery failed.', 0, $exception);
        } catch (Throwable $exception) {
            throw new TransientPushException('FCM transport failed.', 0, $exception);
        }
    }
}
