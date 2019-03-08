<?php
/**
 * Created by AVOCA.IO
 * Website: http://avoca.io
 * User: Jacky
 * Email: hungtran@up5.vn | jacky@youaddon.com
 * Person: tdhungit@gmail.com
 * Skype: tdhungit
 * Git: https://github.com/tdhungit
 */

namespace PHPJEmails\Libraries\Emails;


use PHPJEmails\Config\Config;

class Email_reader
{
    // email login credentials
    private $server = '';
    private $user = '';
    private $pass = '';
    private $secure = 'ssl';
    private $port = 993; // adjust according to server settings

    // mailbox string
    private $mailbox;

    // imap server connection
    public $conn;

    // connect to the server and get the inbox emails
    public function __construct($autoConnect = true)
    {
        $config = Config::$imap;
        $this->server = $config['host'];
        $this->user = $config['username'];
        $this->pass = $config['password'];
        $this->secure = $config['secure'];
        $this->port = $config['port'];

        $this->initMailbox();
        if ($autoConnect) {
            $this->connect();
        }
    }

    public function initMailbox()
    {
        $this->mailbox = '{' . $this->server . ':' . $this->port . '/' . $this->secure . '}';
    }

    public function setMailbox($mailbox)
    {
        $this->mailbox = $mailbox;
    }

    // open the server connection
    // the imap_open function parameters will need to be changed for the particular server
    // these are laid out to connect to a Dreamhost IMAP server
    public function connect()
    {
        $this->conn = imap_open($this->mailbox, $this->user, $this->pass);
    }

    // close the server connection
    public function close()
    {
        $this->inbox = array();
        $this->msg_cnt = 0;

        imap_close($this->conn);
    }

    /**
     *
     * =?x-unknown?B?
     * =?iso-8859-1?Q?
     * =?windows-1252?B?
     *
     * @param string $stringQP
     * @param string $base (optional) charset (IANA, lowercase)
     * @return string UTF-8
     */
    public function decodeToUTF8($stringQP, $base = 'windows-1252')
    {
        $pairs = array(
            '?x-unknown?' => "?$base?"
        );
        $stringQP = strtr($stringQP, $pairs);
        return imap_utf8($stringQP);
    }

    public function getMailBox()
    {
        $ref = $this->mailbox;
        return imap_listmailbox($this->conn, $ref, "*");
    }

    /**
     * @param $msg_number integer|array
     * @return array
     */
    public function get($msg_number)
    {
        if (!is_array($msg_number)) {
            $msg_numbers = [$msg_number];
        } else {
            $msg_numbers = $msg_number;
        }

        $messages = [];
        foreach ($msg_numbers as $number) {
            $overview = imap_fetch_overview($this->conn, $number, 0);
            $structure = imap_fetchstructure($this->conn, $number);
            $header = imap_header($this->conn, $number);

            $from = [];
            if (!empty($header->from)) {
                foreach ($header->from as $ide => $object) {
                    $from[] = [
                        'name' => !empty($object->personal) ? $this->decodeToUTF8($object->personal) : '',
                        'email' => $object->mailbox . "@" . $object->host
                    ];
                }
            }

            $to = [];
            if (!empty($header->to)) {
                foreach ($header->to as $ide => $object) {
                    $to[] = [
                        'name' => !empty($object->personal) ? $this->decodeToUTF8($object->personal) : '',
                        'email' => $object->mailbox . "@" . $object->host
                    ];
                }
            }

            $cc = [];
            if (!empty($header->cc)) {
                foreach ($header->cc as $ide => $object) {
                    $cc[] = [
                        'name' => !empty($object->personal) ? $this->decodeToUTF8($object->personal) : '',
                        'email' => $object->mailbox . "@" . $object->host
                    ];
                }
            }

            $body_message = '';
            $attachments = [];
            if (isset($structure->parts) && is_array($structure->parts) && isset($structure->parts[1])) {
                $part = $structure->parts[0];
                $body_message = imap_fetchbody($this->conn, $number, "1.2");
                $body_message = imap_base64($body_message);
                if (empty($body_message)) {
                    $body_message = imap_fetchbody($this->conn, $number, 1);
                }

                if ($part->encoding == 3) {
                    $body_message = imap_base64($body_message);
                } else if ($part->encoding == 1) {
                    $body_message = imap_8bit($body_message);
                } else if ($part->encoding == 2) {
                    $body_message = imap_binary($body_message);
                } else if ($part->encoding == 4) {
                    $body_message = utf8_encode(quoted_printable_decode($body_message));
                } else if ($part->encoding == 5) {
                    $body_message = $body_message;
                } else {
                    $body_message = imap_qprint($body_message);
                }

                $attachments = $this->getAttachment($structure->parts, $number);
            }

            $date = utf8_decode(imap_utf8($overview[0]->date));
            $subject = quoted_printable_decode(imap_utf8($overview[0]->subject));
            $body_message = strip_tags($body_message);
            $body_message = html_entity_decode($body_message);
            $body_message = htmlspecialchars($body_message);

            $messages[$number] = [
                'index' => $number,
                'from' => $from,
                'to' => $to,
                'cc' => $cc,
                'date' => $date,
                'subject' => $subject,
                'attachments' => $attachments,
                'message' => $body_message,
            ];
        }

        return $messages;
    }

    public function getAttachment($parts, $msg_number, $parentsection = "")
    {
        $attachments = array();

        foreach ($parts as $subsection => $part) {
            $section = $parentsection . ($subsection + 1);
            if (isset($part->parts)) {
                // some mails have one extra dimension
                return $this->getAttachment($part->parts, $section . ".");
            } elseif (isset($part->disposition)) {
                if (in_array(strtolower($part->disposition), array('attachment', 'inline'))) {
                    $data = imap_fetchbody($this->conn, $msg_number, $section);
                    $filename = $this->decodeToUTF8($part->dparameters[0]->value);
                    $size = strlen($data);

                    if ($part->encoding == 3) { // 3 = BASE64
                        $data = base64_decode($data);
                    } elseif ($part->encoding == 4) { // 4 = QUOTED-PRINTABLE
                        $data = quoted_printable_decode($data);
                    }

                    $attachments[] = [
                        'filename' => $filename,
                        'size' => $size,
                        'attachment' => $data
                    ];
                }
            }
        }

        return $attachments;
    }

    public function saveAttachment($attachments)
    {
        foreach ($attachments as $key => $attachment) {
            $name = $attachment['name'];
            $contents = $attachment['attachment'];
            file_put_contents($name, $contents);
        }
    }

    /**
     * @param $criteria
     *  ALL - return all messages matching the rest of the criteria
     * ANSWERED - match messages with the \\ANSWERED flag set
     * BCC "string" - match messages with "string" in the Bcc: field
     * BEFORE "date" - match messages with Date: before "date"
     * BODY "string" - match messages with "string" in the body of the message
     * CC "string" - match messages with "string" in the Cc: field
     * DELETED - match deleted messages
     * FLAGGED - match messages with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
     * FROM "string" - match messages with "string" in the From: field
     * KEYWORD "string" - match messages with "string" as a keyword
     * NEW - match new messages
     * OLD - match old messages
     * ON "date" - match messages with Date: matching "date"
     * RECENT - match messages with the \\RECENT flag set
     * SEEN - match messages that have been read (the \\SEEN flag is set)
     * SINCE "date" - match messages with Date: after "date"
     * SUBJECT "string" - match messages with "string" in the Subject:
     * TEXT "string" - match messages with text "string"
     * TO "string" - match messages with "string" in the To:
     * UNANSWERED - match messages that have not been answered
     * UNDELETED - match messages that are not deleted
     * UNFLAGGED - match messages that are not flagged
     * UNKEYWORD "string" - match messages that do not have the keyword "string"
     * UNSEEN - match messages which have not been read yet
     * example: SINCE "7 Mar 2019"
     * @return array [message]
     */
    public function search($criteria)
    {
        $msg_numbers = imap_search($this->conn, $criteria);
        return $this->get($msg_numbers);
    }

    /**
     * Search message by date start and date end
     *
     * @param $start string Y-m-d
     * @param $end string Y-m-d
     * @return array
     */
    public function byDate($start, $end)
    {
        try {
            $startDate = date_create($start);
            $endDate = date_create($end);

            $startStr = $startDate->format('d F Y');
            date_add($endDate, date_interval_create_from_date_string('1 day'));
            $endStr = $endDate->format('d F Y');

            $msg_numbers = imap_search($this->conn, 'BEFORE "' . $endStr . '" SINCE "' . $startStr . '"');
            return $this->get($msg_numbers);
        } catch (\Exception $exception) {
            return [];
        }
    }

    // move the message to a new folder
    public function move($msg_index, $folder = 'INBOX.Processed')
    {
        // move on server
        imap_mail_move($this->conn, $msg_index, $folder);
        imap_expunge($this->conn);
    }
}