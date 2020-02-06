<?php
class Facebook extends WebPages {
	public $cookies=[];
	public $email="",$pass="";
	public function __construct($email="",$pass="") {
		$this->email=$email;
		$this->pass=$pass;
	}
	public function Login($email="",$pass="") {
		$email=(strlen($email)>0)?$email:$this->email;
		$pass=(strlen($pass)>0)?$pass:$this->pass;
		if (strlen($email)<=0||strlen($pass)<=0) return array("success"=>false,"error"=>"Please ensure email and password");
		$domain="https://m.facebook.com";
		$login=$this->Connect(array("url"=>$domain."/login","cookies"=>array("noscript"=>"1")));
		$form=$this->ParseForm($login["result"]);
		if ($form["success"]) {
			$form["data"]["email"]=$email;
			$form["data"]["pass"]=$pass;
			$form["data"]["login"]="Log In";
			$url=$form["url"];
			$parsed=parse_url($url);
			if (!isset($parsed["host"])) {
				$url=$domain.$url;
			}
			$login=$this->Connect(array("url"=>$url,"method"=>"POST","data"=>$form["data"]));
			if ($login["httpcode"]!=302) return array("success"=>false,"error"=>"Facebook changed type of login");
			$url_dec=parse_url($login["location"]);
			if ($url_dec["path"]=="/home.php") {
				$this->cookies=$login["cookies"];
				return array("success"=>true,"cookies"=>$login["cookies"]);
			} elseif ($url_dec["path"]=="/login/") {
				$log_error=$this->Connect(array("url"=>$login["location"],"cookies"=>$login["cookies"]));
				$log_err_text=$this->Cut($log_error["result"],"<div class=\"z\">","</div>");
				return array("success"=>false,"error"=>$log_err_text);
			} else {
				return array("success"=>false,"error"=>"Problem with script");
			}
		} else {
			return array("success"=>false,"error"=>"Can't get form params");
		}
	}
	public function GetMessengerList($cookies=array()) {
		$cookies=(count($cookies)>0)?$cookies:$this->cookies;
		$html=$this->Connect(array("url"=>"https://m.facebook.com/messages/","cookies"=>$cookies))["result"];
		$html=explode("#search_section",$html)[1];
		$html=explode("see_older_threads",$html);
		$msg=explode("<table",$html[0]);
		$messages=array();
		for ($i=1;$i<count($msg);$i++) {
			$msg_content=explode("</table>",$msg[$i])[0];
			$m=explode("<a href=\"",$msg_content)[1];
			$m=explode("\">",$m);
			$url=$m[0];
			$username=explode("<",$m[1]);
			$active=substr($username[1],0,3)=="img";
			$last_msg=explode("<span",$msg_content)[1];
			$last_msg=explode(">",$last_msg)[1];
			$last_msg=explode("</span",$last_msg)[0];
			$username=$username[0];
			$last_msg_date=explode("<abbr>",$msg_content)[1];
			$last_msg_date=explode("</abbr>",$last_msg_date)[0];
			$messages[]=array("url"=>$url,"username"=>$username,"active"=>$active,"last_msg"=>$last_msg,"last_msg_date"=>$last_msg_date);
		}
		return array("success"=>true,"messages"=>$messages);
	}
	public function SendMessage($cookies=array(),$fbid,$message) {
		$cookies=(count($cookies)>0)?$cookies:$this->cookies;
		$html=$this->Connect(array("url"=>"https://m.facebook.com/messages/read/?fbid=".$fbid,"cookies"=>$cookies));
		$form_send=$this->ParseForm($html["result"],1);
		if (strpos($form_send["url"],"messages/send")===false||$form_send["method"]!="post") {
			return array("success"=>false,"error"=>"Can't get form params");
		}
		$form_send["data"]["send"]="Send";
		$form_send["data"]["body"]=$message;
		$send_res=$this->Connect(array("url"=>"https://m.facebook.com".$form_send["url"],"method"=>"POST","data"=>$form_send["data"],"cookies"=>$html["cookies"]));
		if (strpos($send_res["location"],"request_type=send_success")!==false) {
			return array("success"=>true);
		} else {
			return array("success"=>false,"error"=>"Can't send form");
		}
	}
	public function CreateGroup($cookies=array(),$fbids,$message) {
		$cookies=(count($cookies)>0)?$cookies:$this->cookies;
		if (count($fbids)<=0) return array("success"=>false,"error"=>"fbids array req");
		$str="";
		for ($i=0;$i<count($fbids);$i++) {
			$str.="ids%5B".$i."%5D=".$fbids[$i]."&";
		}
		$html=$this->Connect(array("url"=>"https://mbasic.facebook.com/messages/compose/?".$str."is_from_friend_selector=1&_rdr","cookies"=>$cookies));
		$form_send=$this->ParseForm($html["result"],1);
		$form_send["data"]["body"]=$message;
		$form_send["data"]["Send"]="Send";
		$send_res=$this->Connect(array("url"=>"https://m.facebook.com".$form_send["url"],"method"=>"POST","data"=>$form_send["data"],"cookies"=>$html["cookies"]));
		if (strpos($send_res["location"],"request_type=send_success")!==false) {
			return array("success"=>true);
		} else {
			return array("success"=>false,"error"=>"Can't send form");
		}
	}
	public function ChangeColor($cookies=array(),$other_id,$theme_id,$fb_dtsg) {
		$cookies=(count($cookies)>0)?$cookies:$this->cookies;
		//fb_dtsg
		$my_id=$cookies["c_user"];
		$queries=array("o0"=>array("doc_id"=>"1727493033983591","query_params"=>array("data"=>array("client_mutation_id"=>"0","actor_id"=>$my_id,"thread_id"=>$other_id,"theme_id"=>$theme_id,"source"=>"SETTINGS"))));
		$payload="fb_dtsg=".$fb_dtsg."&queries=".urlencode(json_encode($queries));
		$html=$this->Connect(array("url"=>"https://www.facebook.com/api/graphqlbatch/","method"=>"post","cookies"=>$cookies,"payload"=>$payload));
		return $html["result"];
	}
}
?>