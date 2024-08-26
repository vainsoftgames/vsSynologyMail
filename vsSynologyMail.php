<?php

class vsSynologyMail {
    // @var string Host of the Synology MailPlus server
    public $host;
    // @var int Port number for the Synology MailPlus server
    public $port;
    // @var string Authentication token for API access
    public $token;
    
    // Used for Authentication
    public $user;
    public $pass;
    
    private $client_mail = null;

    /**
     * Constructor: Initializes the Synology MailPlus API client.
     */
    public function __construct($host, $port, $user, $pass){
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }
    
    /**
     * Generates the full URL to the Synology MailPlus API.
     * 
     * @return string The full API URL.
     */
    protected function genURL($endpoint='entry'){
        return ($this->host .':'. $this->port .'/webapi/'. $endpoint .'.cgi');
    }

    /**
     * Configures common cURL options for SSL and authorization headers.
     * 
     * @param resource $ch The cURL handle.
     * @param bool $headers Whether to include authorization headers.
     */
    protected function curlSetup($ch, $headers=true){
        if($headers){
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Note: Security risk in production
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Note: Security risk in production
    }

    /**
     * Executes a cURL request with provided parameters and extracts a specific part of the response.
     * 
     * @param array $params The parameters for the API request.
     * @param string $key The key of the data to retrieve from the response.
     * 
     * @return mixed The extracted data or null if not found/error.
     */
    protected function curlPayload($params, $key=false, $headers=true, $payload=false, $endpoint='entry'){
        $queryString = http_build_query($params);
        $ch = curl_init("{$this->genURL($endpoint)}?{$queryString}");
        $this->curlSetup($ch, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($payload){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
		var_dump($response);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if($key) return $data['data'][$key] ?? $data;
        else return $data;
    }

    /**
     * Logs in to the Synology MailPlus server.
     * 
     * @return bool True if login is successful, false otherwise.
     */
    public function login(){
        $params = [
            'api' => 'SYNO.API.Auth',
            'method' => 'login',
            'version' => '3',
            'account' => $this->user,
            'passwd' => $this->pass,
            'session' => 'MailPlus',
            'format' => 'sid'
        ];

        $response = $this->curlPayload($params, 'sid', false);
        if ($response) {
            $this->token = $response;
            return true;
        }
        return false;
    }

    /**
     * Retrieves emails from the Synology MailPlus server.
     * 
     * @param int $limit The number of emails to retrieve.
     * 
     * @return array The list of emails.
     */
    public function getEmails($limit = 10){
        $params = [
            'api' => 'SYNO.MailPlus.Message',
            'method' => 'list',
            'version' => '1',
            'limit' => $limit,
            'offset' => 0,
            'mailbox' => 'inbox',
        ];

        return $this->curlPayload($params, 'messages');
    }

    /**
     * Sends an email through the Synology MailPlus server.
     * 
     * @param array $email The email details.
     * 
     * @return bool True if the email is sent successfully, false otherwise.
     */
    public function sendMessage($email){
        $params = [
            'api' => 'SYNO.MailPlus.Message',
            'method' => 'send',
            'version' => '1'
        ];

        $payload = [
            'mail_to' => $email['to'],
            'mail_subject' => $email['subject'],
            'mail_content' => $email['content'],
        ];

        if (isset($email['cc'])) {
            $payload['mail_cc'] = $email['cc'];
        }
        if (isset($email['bcc'])) {
            $payload['mail_bcc'] = $email['bcc'];
        }

        $response = $this->curlPayload($params, null, true, $payload);
        return isset($response['success']) && $response['success'];
    }
}

?>
