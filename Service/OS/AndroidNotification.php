<?php

namespace RMS\PushNotificationsBundle\Service\OS;

use Psr\Log\LoggerInterface;
use RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException,
    RMS\PushNotificationsBundle\Message\AndroidMessage,
    RMS\PushNotificationsBundle\Message\MessageInterface;
use Buzz\Browser;

class AndroidNotification implements OSNotificationServiceInterface
{
    /**
     * Username for auth
     *
     * @var string
     */
    protected $username;

    /**
     * Password for auth
     *
     * @var string
     */
    protected $password;

    /**
     * The source of the notification
     * eg com.example.myapp
     *
     * @var string
     */
    protected $source;

    /**
     * Authentication token
     *
     * @var string
     */
    protected $authToken;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param $username
     * @param $password
     * @param $source
     */
    public function __construct($username, $password, $source)
    {
        $this->username = $username;
        $this->password = $password;
        $this->source = $source;
        $this->authToken = "";
    }

    /**
     * Set the logger to use
     *
     * @param LoggerInterface $logger
     * @return mixed|void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sends a C2DM message
     * This assumes that a valid auth token can be obtained
     *
     * @param \RMS\PushNotificationsBundle\Message\MessageInterface $message
     * @throws \RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException
     * @return bool
     */
    public function send(MessageInterface $message)
    {
        if (!$message instanceof AndroidMessage) {
            throw new InvalidMessageTypeException(sprintf("Message type '%s' not supported by C2DM", get_class($message)));
        }

        if ($this->getAuthToken()) {
            $headers[] = "Authorization: GoogleLogin auth=" . $this->authToken;
            $data = $message->getMessageBody();

            $buzz = new Browser();
            $buzz->getClient()->setVerifyPeer(false);
            $response = $buzz->post("https://android.apis.google.com/c2dm/send", $headers, http_build_query($data));
            $success = preg_match("/^id=/", $response->getContent()) > 0;
            if (!$success) {
                list($errorKey, $errorMessage) = explode("=", $response->getContent());
                $this->logger->error("C2DM error received: {error}", array("error" => $errorMessage));
            }
            return $success;
        }

        return false;
    }


    /**
     * Gets a valid authentication token
     *
     * @return bool
     */
    protected function getAuthToken()
    {
        $data = array(
            "Email"         => $this->username,
            "Passwd"        => $this->password,
            "accountType"   => "HOSTED_OR_GOOGLE",
            "source"        => $this->source,
            "service"       => "ac2dm"
        );

        $buzz = new Browser();
        $buzz->getClient()->setVerifyPeer(false);
        $response = $buzz->post("https://www.google.com/accounts/ClientLogin", array(), http_build_query($data));
        if ($response->getStatusCode() !== 200) {
            $this->logger->error("C2DM authentication status code: {statusCode}", array("statusCode" => $response->getStatusCode()));
            return false;
        }

        preg_match("/Auth=([a-z0-9_\-]+)/i", $response->getContent(), $matches);
        $this->authToken = $matches[1];
        return true;
    }
}
