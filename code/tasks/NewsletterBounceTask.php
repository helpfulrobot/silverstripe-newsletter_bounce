<?php

/**
 * Class NewsletterBounceTask
 */
class NewsletterBounceTask extends BuildTask
{

    /**
     * @var string
     */
    private static $email = '';

    /**
     * @var string
     */
    private static $password = '';

    /**
     * @var string
     */
    private static $server = '';

    /**
     * @var int
     */
    private static $blacklistLimit = 5;

    /**
     * @var string
     */
    private static $errorName = '';

    /**
     * @var string
     */
    private static $errorValue = '';

    /**
     * @var string
     */
    protected $title = 'Mark bounced newsletter emails';

    /**
     * @var string
     */
    protected $description = "Opens up an e-mail inbox and looks for bounces.";

    /**
     * @var bool
     */
    protected $debug = true;

    /**
     * @var int $bounces amount of bounces count.
     */
    protected $bounces = 0;

    /**
     * @param $request
     */
    function run($request)
    {
        $this->debug = false;
        $server = self::$server;
        /** @var resource $mailbox */
        $mailbox = imap_open($server, self::$email, self::$password);
        if ($mailbox) {
            $emails = imap_search($mailbox, 'UNFLAGGED', SE_UID);
            if ($emails) {
                foreach ($emails as $emailID) {
                    $emailFlags = imap_fetch_overview($mailbox, $emailID);
                    $isBounce = array(false, false, false);
                    if ($this->debug) {
                        echo "<hr /><hr /><hr /><hr />$emailID<hr /><pre>";
                    }
                    if (!$emailFlags[0]->flagged) { // extra check to make sure we're not checking an already checked e-mail.
                        $isBounce = $this->checkEmail($mailbox, $emailID);
                    }
                    if ($isBounce[0] && $isBounce[1]) { // Don't check if there's an errormessage
                        $this->isBounced($mailbox, $emailID, $isBounce);
                    }
                    if ($this->debug) {
                        echo "</pre>";
                    }
                }
            }
            imap_close($mailbox);
            echo $this->bounces . " Bounces found";
        } else {
            user_error("Can not find mailbox", E_USER_NOTICE);
        }
    }

    /**
     * @param $mailbox
     * @param $emailID
     * @return array
     */
    private function checkEmail($mailbox, $emailID)
    {
        $bounce = false;
        $to = "";
        $error = false;
        $headers = imap_body($mailbox, $emailID, FT_UID);
        $headers = explode("\n", $headers);
        foreach ($headers as $header) {
            $header = explode(':', $header);
            if (count($header) == 2) {
                list($name, $value) = $header;
                // Strip the spaces at frond and end, they break the other if statements.
                $name = trim($name);
                $value = trim($value);
                if ($this->debug) {
                    echo "$name<br />$value";
                }
                if ($name == self::$errorName && $value == self::$errorValue) {
                    $bounce = true;
                }
                if ($name == "To") {
                    $to = Convert::raw2sql($value);
                }
                if ($name == 'Diagnostic-Code') {
                    $error = 'Diagnostic-Code: x-unix; user unknown';
                }
                if ($bounce && $to && $error) {
                    break; // Break when we have a bouncer, a $to and a useful $error. The useful error can be skipped, it'll be false.
                }
            }
        }
        return (array(
            $bounce,
            $to,
            $error
        ));
    }

    /**
     * @param $mailbox
     * @param $emailID
     * @param $isBounce
     */
    private function isBounced($mailbox, $emailID, $isBounce)
    {
        $stripTags = array(
            '<',
            '>'
        );
        $to = str_replace($stripTags, array('',''), $isBounce[1]);
        $error = $isBounce[2];
        /** @var Recipient $recipient */
        $recipient = Recipient::get()
            ->filter(array("Email" => $to))
            ->first();
        if ($recipient->BouncedCount == self::$blacklistLimit) {
            $recipient->BlacklistedEmail = true;
            $recipient->write();
        } else {
            $recipient->BouncedCount = $recipient->BouncedCount + 1;
            $recipient->write();

            /** @var NewsletterEmailBounceRecord $record */
            $record = NewsletterEmailBounceRecord::get()
                ->filter(array("BounceEmail" => $to));
            if (!$record->count()) {
                $record = NewsletterEmailBounceRecord::create();
                $record->BounceEmail = $to;
                $record->BounceMessage = $error;
                $record->RecipientID = $recipient->ID;
            }
            $record->LastBounceTime = SS_Datetime::create()->now();
            $record->write();
        }
        imap_setflag_full($mailbox, $emailID, '\flagged', ST_UID);
        $this->bounces = $this->bounces + 1;
    }

    /**
     * @return string
     */
    public static function getEmail()
    {
        return self::$email;
    }

    /**
     * @param string $email
     */
    public static function setEmail($email)
    {
        self::$email = $email;
    }

    /**
     * @return string
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * @return string
     */
    public static function getServer()
    {
        return self::$server;
    }

    /**
     * @param string $server
     */
    public static function setServer($server)
    {
        self::$server = $server;
    }

    /**
     * @return int
     */
    public static function getBlacklistLimit()
    {
        return self::$blacklistLimit;
    }

    /**
     * @param int $blacklistLimit
     */
    public static function setBlacklistLimit($blacklistLimit)
    {
        self::$blacklistLimit = $blacklistLimit;
    }

    /**
     * @return string
     */
    public static function getErrorName()
    {
        return self::$errorName;
    }

    /**
     * @param string $errorName
     */
    public static function setErrorName($errorName)
    {
        self::$errorName = $errorName;
    }

    /**
     * @return string
     */
    public static function getErrorValue()
    {
        return self::$errorValue;
    }

    /**
     * @param string $errorValue
     */
    public static function setErrorValue($errorValue)
    {
        self::$errorValue = $errorValue;
    }


}

