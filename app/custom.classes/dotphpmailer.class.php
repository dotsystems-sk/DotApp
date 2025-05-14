<?php
	require_once __ROOTDIR__ . "/app/custom.classes/phpmailer/phpmailer.class.php";
	
	class dotphpmailer {
		
		private $phpmailer;
		private $dotapp;
		
		function __construct($dotapp) {
			$this->phpmailer = new phpmailer();
			$this->dotapp = $dotapp;
		}
		
		/*
			Kazdy sender musi mat definovanu funkciu SEND_EMAIL($erp_sender_email_id,$recipient,$subject,$htmltext);
			V podstate co sa emailov tyka absolutne vobec netreba definovat dalsi sender, ale tato moznost tu aj tak je :)
			Skor sa to vyuzije pri SMSkach, nakolko tam je to o poskytovatelovi a jeho API alebo je to o GSM brane.
		*/
		public function send_email($erp_sender_email_id,$recipient,$subject="",$htmltext="") {
			$query = "SELECT * FROM erp_sender_email WHERE id = '".$erp_sender_email_id."';";
			$sender_email = $this->dotapp->db->select_db("main")->query_first($query);
			$this->set_mailer($sender_email['json_settings']);
			$this->phpmailer->setFrom($sender_email['from_email'], $sender_email['from_email_name']);
			$this->phpmailer->CharSet = 'UTF-8';
			$this->phpmailer->Encoding = 'base64';
			if (is_array($recipient)) {
				$i=0;
				foreach ($recipient as $key=>$val) {
					if ($i == 0) {
						$this->phpmailer->AddAddress($val, '');
					} else {
						$this->phpmailer->AddBCC($val, $val);
					}
					$i++;
				}				
			} else {
				$this->phpmailer->AddAddress($recipient, '');
			}			
			$this->phpmailer->Subject = $subject;
			$this->phpmailer->MsgHTML($htmltext);
			if (strlen($sender_email['reply_to']) > 3) $this->phpmailer->addReplyTo($sender_email['reply_to'], $sender_email['reply_to_name']);
			
			if(!$this->phpmailer->Send()) {
				$reply['status'] = 0;
				$reply['status_txt'] = "Nepodarilo sa odoslať email";
				$reply['debug']['ErrorInfo'] = $this->phpmailer->ErrorInfo;
			} else {
				$reply['status'] = 1;
			}
			return($this->dotapp->ajax_reply($reply));
		}
		
		public function set_mailer($json) {
			$settings = $this->dotapp->lowercase_arraykeys(json_decode($json,true));
			if (isSet($settings['issmtp'])) $this->phpmailer->IsSMTP();
			if (isSet($settings['smtpkeepalive'])) $this->phpmailer->SMTPKeepAlive = $settings['smtpkeepalive'];
			if (isSet($settings['timeout'])) $this->phpmailer->Timeout = $settings['timeout'];
			if (isSet($settings['ishtml'])) $this->phpmailer->IsHTML($settings['ishtml']);
			if (isSet($settings['host'])) $this->phpmailer->Host = $settings['host'];
			if (isSet($settings['smtpauth'])) $this->phpmailer->SMTPAuth = $settings['smtpauth'];
			if (isSet($settings['username'])) $this->phpmailer->Username = $settings['username'];
			if (isSet($settings['password'])) $this->phpmailer->Password = $settings['password']; 
			if (isSet($settings['smtpsecure'])) $this->phpmailer->SMTPSecure = $settings['smtpsecure'];
			if (isSet($settings['port'])) $this->phpmailer->Port = $settings['port'];
			if (isSet($settings['smtpdebug'])) $mail->SMTPDebug = $settings['smtpdebug'];
		}
	}
?>