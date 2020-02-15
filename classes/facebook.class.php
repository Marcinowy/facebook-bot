<?php
class Facebook extends WebPages
{
    const DOMAIN = 'https://m.facebook.com';
    private $cookies, $email, $pass;
    public $myID;

    private function getLoginFormData()
    {
        $login = $this->Connect(array(
            'url' => self::DOMAIN . '/login',
            'cookies' => array('noscript' => '1')
        ));
        $form = $this->ParseForm($login['result']);

        if (!$form['success']) return false;

        $form['data'] = array_merge($form['data'], array(
            'email' => $this->email,
            'pass' => $this->pass,
            'login' => 'Log In'
        ));

        $parsed = parse_url($form['url']);
        if (!isset($parsed['host'])) {
            $form['url'] = self::DOMAIN . $form['url'];
        }

        return $form;
    }
    
    private function extractSingleConversation($messageContent)
    {
        $m = explode('<a href="', $messageContent)[1];
        $m = explode('">', $m);

        $url = $m[0];

        $username = explode('<', $m[1]);

        $active = substr($username[1], 0, 3) == 'img';

        $lastMessage = explode('<span', $messageContent)[1];
        $lastMessage = explode('>', $lastMessage)[1];
        $lastMessage = explode('</span', $lastMessage)[0];

        $username = $username[0];

        $lastMessageDate = explode('<abbr>', $messageContent)[1];
        $lastMessageDate = explode('</abbr>', $lastMessageDate)[0];

        return array(
            'url' => $url,
            'username' => $username,
            'active' => $active,
            'lastMessage' => $lastMessage,
            'lastMessageDate' => $lastMessageDate
        );
    }

    private function getDtsg()
    {
        $html = $this->Connect(array(
            'url' => self::DOMAIN,
            'cookies' => $this->cookies
        ));
        $this->cookies = $html['cookies'];
        
        $dtsg=$this->Cut($html['result'],'fb_dtsg" value="','"');
        
        if (strpos($dtsg,':')!==false) {
            return $dtsg;
        }
        return false;
    }

    private function gqlResponseFilter($var)
    {
        return (strlen(trim($var))>0);
    }
    
    public function __construct(string $email, string $pass)
    {
        if (strlen($email) <= 0 || strlen($pass) <= 0)
            return array('success' => false, 'error' => 'Please ensure email and password');

        $this->email = $email;
        $this->pass = $pass;
    }

    public function Login()
    {
        $form = $this->getLoginFormData();
        
        if ($form) {
            $login = $this->Connect(array(
                'url' => $form['url'],
                'method' => 'POST',
                'data' => $form['data']
            ));
            if (!in_array($login['httpcode'], array(302, 200)))
                return array('success' => false,'error' => 'Facebook changed type of login');
            
            $urlDecoded = parse_url($login['location']);
            
            if ($urlDecoded['path'] == '/home.php') {
                $this->cookies = $login['cookies'];
                $this->myID = $this->cookies['c_user'];
                return array('success' => true);
            } elseif ($urlDecoded['path'] == '/login/') {
                $logError = $this->Connect(array(
                    'url' => $login['location'],
                    'cookies' => $login['cookies']
                ));
                $errorText = $this->Cut($logError['result'], '<div class="z">', '</div>');
                return array('success' => false, 'error' => $errorText);
            } else {
                $errorText = $this->Cut($login['result'], '<title>', '</title>');
                return array('success' => false, 'error' => 'Problem with script: ' . $errorText);
            }
        } else {
            return array('success' => false, 'error' => 'Can\'t get form params');
        }
    }

    public function ManualSetCookies($cookies)
    {
        if (is_array($cookies))
            $this->cookies = $cookies;
        else {
            $cookie = explode(';', $cookies);
            for ($i = 0; $i < count($cookie); $i++) {
                $thisCookie = trim($cookie[$i]);
                if (strlen($thisCookie)<=0)
                    continue;
                
                $thisCookie = explode('=', $thisCookie);
                $cookiesArray[$thisCookie[0]] = $thisCookie[1];
            }

            $this->cookies = $cookiesArray;
            $this->myID = $this->cookies['c_user'];
        }
    }

    public function GetMessengerList()
    {
        $html = $this->Connect(array(
            'url' => self::DOMAIN . '/messages/',
            'cookies' => $this->cookies
        ))['result'];

        $html = explode('#search_section', $html)[1];
        $html = explode('see_older_threads', $html);
        $message = explode('<table', $html[0]);
        
        $messages = array();
        
        for ($i = 1; $i < count($message); $i++) {
            $messageContent = explode('</table>', $message[$i])[0];
            $messages[] = $this->extractSingleConversation($messageContent);
        }
        
        return array('success' => true, 'messages' => $messages);
    }
    
    public function SendMessage($fbid, $message = 'This is an example message')
    {
        $html = $this->Connect(array(
            'url' => self::DOMAIN . '/messages/read/?fbid=' . $fbid,
            'cookies' => $this->cookies
        ));

        $formSend = $this->ParseForm($html['result'], 1);
        if (strpos($formSend['url'], 'messages/send') === false || $formSend['method'] != 'post') {
            return array('success' => false, 'error' => 'Cannot get form params');
        }
        $formSend['data'] = array_merge($formSend['data'], array(
            'send' => 'Send',
            'body' => $message
        ));
        
        $result = $this->Connect(array(
            'url' => self::DOMAIN . $formSend['url'],
            'method' => 'POST',
            'data' => $formSend['data'],
            'cookies' => $html['cookies']
        ));

        if (strpos($result['location'],'request_type=send_success') !== false) {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Cannot send message');
        }
    }
    
    public function CreateGroup($fbids, $message = 'This is an example message')
    {
        if (!is_array($fbids))
            return array('success' => false, 'error' => '$fbids should be an array');

        if (count($fbids) <= 0)
            return array('success' => false, 'error' => 'fbids array req');

        $getData = [];
        for ($i = 0; $i < count($fbids); $i++) {
            $getData['ids[' . $i . ']'] = $fbids[$i];
        }
        $getData['is_from_friend_selector'] = 1;
        $getData['_rdr'] = '';
        
        $html = $this->Connect(array(
            'url' => self::DOMAIN . '/messages/compose/?' . http_build_query($getData),
            'cookies' => $this->cookies
        ));

        $formSend = $this->ParseForm($html['result'], 1);
        $formSend['data'] = array_merge($formSend['data'], array(
            'body' => $message,
            'send' => 'Send'
        ));

        $sendResult = $this->Connect(array(
            'url' => self::DOMAIN . $formSend['url'],
            'method' => 'POST',
            'data' => $formSend['data'],
            'cookies' => $html['cookies']
        ));

        if (strpos($sendResult['location'], 'request_type=send_success') !== false) {
            return array('success' => true);
        } else {
            return array('success' => false,'error' => 'Cannot send message');
        }
    }
    
    public function ChangeColor($otherID, $themeID) {
        $queries = array(
            'o0' => array(
                'doc_id' => '1727493033983591',
                'query_params' => array(
                    'data' => array(
                        'client_mutation_id' => '0',
                        'actor_id' => $this->myID,
                        'thread_id' => $otherID,
                        'theme_id' => $themeID,
                        'source' => 'SETTINGS'
                    )
                )
            )
        );

        $payload = array(
            'fb_dtsg' => $this->getDtsg(),
            'queries' => json_encode($queries)
        );
        
        $html = $this->Connect(array(
            'url' => 'https://www.facebook.com/api/graphqlbatch/',
            'method' => 'post',
            'cookies' => $this->cookies,
            'payload' => http_build_query($payload)
        ));

        $response = explode('    ', $html['result']);
        $response = array_filter($response, array($this, 'gqlResponseFilter'));
        $response = array_values($response);
        $response = json_decode($response[1], true);
        
        return array('success' => ($response['successful_results'] > 0));
    }
}
?>