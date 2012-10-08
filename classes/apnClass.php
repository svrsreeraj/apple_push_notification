<?php
class APNS 
	{
		private $apnsData;
		private $showErrors 		= true;
		private $logErrors 			= true;
		private $logPath 			= '/your/absolute/path/to/log/file.log';
		private $logMaxSize 		= 1048576; // max log size before it is truncated
		private $certificate 		= '/your/absolute/path/for/production/certificate/.pem';
		private $ssl 				= 'ssl://gateway.push.apple.com:2195';
		private $feedback 			= 'ssl://feedback.push.apple.com:2196';
		private $sandboxCertificate = '/your/absolute/path/for/sandbox/certificate.pem'; //change this to your development certificate absolute path
		private $sandboxSsl 		= 'ssl://gateway.sandbox.push.apple.com:2195';
		private $sandboxFeedback 	= 'ssl://feedback.sandbox.push.apple.com:2196';
		private $message;
		private $pushArray;
		
		function __construct() 
			{
				$this->checkSetup();
				$this->apnsData = array(
						'production'=>array('certificate'=>$this->certificate,'ssl'=>$this->ssl,'feedback'=>$this->feedback), 
						'sandbox'=>array('certificate'=>$this->sandboxCertificate,'ssl'=>$this->sandboxSsl,'feedback'=>$this->sandboxFeedback)
						);
			}
		
		private function checkSetup()	
			{
				if(!file_exists($this->certificate)) 		$this->_triggerError('Missing Production Certificate.', E_USER_ERROR);
				if(!file_exists($this->sandboxCertificate)) $this->_triggerError('Missing Sandbox Certificate.', E_USER_ERROR);
				clearstatcache();
	    		$certificateMod 			= 	substr(sprintf('%o', fileperms($this->certificate)), -3);
				$sandboxCertificateMod 		= 	substr(sprintf('%o', fileperms($this->sandboxCertificate)), -3); 
				if($certificateMod>755)			$this->_triggerError('Production Certificate is insecure! Suggest chmod 644.');
				if($sandboxCertificateMod>755)  $this->_triggerError('Sandbox Certificate is insecure! Suggest chmod 644.');
			}
	
		public function _pushMessage($token, $development)
			{
				$message	=	$this->_jsonEncode($this->message);
				if(strlen($message)==0) 	$this->_triggerError('Missing message.', E_USER_ERROR);
				if(strlen($token)==0) 		$this->_triggerError('Missing message token.', E_USER_ERROR);
				if(strlen($development)==0) $this->_triggerError('Missing development status.', E_USER_ERROR);
			
				$ctx = stream_context_create();
				stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
				$fp = stream_socket_client($this->apnsData[$development]['ssl'], $error, $errorString, 60, STREAM_CLIENT_CONNECT, $ctx);
			
				if(!$fp)
					{
						$this->_triggerError("Failed to connect to APNS: {$error} {$errorString}.");
					}
				else 
					{
						$msg 	= chr(0).pack("n",32).pack('H*',$token).pack("n",strlen($message)).$message;
						$fwrite = fwrite($fp, $msg);
						if(!$fwrite) 
							{
								//Failed
								$this->_triggerError("Failed writing to stream.", E_USER_ERROR);
							}
						else 
							{
								//success
								$this->_triggerError("Success");
							}
					}
				fclose($fp);
				$this->_checkFeedback($development);
			}
		public function newMessage()
			{
				$this->message 					= array();
				$this->message['aps'] 			= array();
			}
		private function _checkFeedback($development)
			{
				$ctx = stream_context_create();
				stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
				stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
				$fp = stream_socket_client($this->apnsData[$development]['feedback'], $error, $errorString, 60, STREAM_CLIENT_CONNECT, $ctx);
			
				if(!$fp) $this->_triggerError("Failed to connect to device: {$error} {$errorString}.");
				while ($devcon = fread($fp, 38))
					{
						$arr	= unpack("H*", $devcon);
						$rawhex	= trim(implode("", $arr));
						$token 	= substr($rawhex, 12, 64);
						if(!empty($token))
							{
								$this->_triggerError("Unregistering Device Token: {$token}.");
							}
					}
				fclose($fp);
			}
		
		private function _triggerError($error, $type=E_USER_NOTICE)
			{
				$backtrace	= debug_backtrace();
				$backtrace	= array_reverse($backtrace);
				$error 		.= "\n";
				$i	=	1;
				
				foreach($backtrace as $errorcode)
					{
						$file 	= ($errorcode['file']!='') ? "-> File: ".basename($errorcode['file'])." (line ".$errorcode['line'].")":"";
						$error .= "\n\t".$i.") ".$errorcode['class']."::".$errorcode['function']." {$file}";
						$i++;
					}
				$error .= "\n\n";
				if($this->logErrors && file_exists($this->logPath))
					{
						if(filesize($this->logPath) > $this->logMaxSize) $fh = fopen($this->logPath, 'w');
						else $fh = fopen($this->logPath, 'a');
						fwrite($fh, $error);
						fclose($fh);
					}
				if($this->showErrors) echo "<br><br>".$error;
			}
		
				
		public function addMessageAlert($alert=NULL, $actionlockey=NULL, $lockey=NULL, $locargs=NULL)
			{
				if(!$this->message)	$this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
				if(isset($this->message['aps']['alert']))
					{
						unset($this->message['aps']['alert']);
						$this->_triggerError('An existing alert was already created but not delivered. The previous alert has been removed.');
					}
				switch(true)
					{
						case(!empty($alert) && empty($actionlockey) && empty($lockey) && empty($locargs)):
							if(!is_string($alert)) $this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
							$this->message['aps']['alert'] = (string)$alert;
							break;
						case(!empty($alert) && !empty($actionlockey) && empty($lockey) && empty($locargs)):
							if		(!is_string($alert)) 		$this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
							else if	(!is_string($actionlockey)) $this->_triggerError('Invalid Action Loc Key Format. See documentation for correct procedure.', E_USER_ERROR);
							$this->message['aps']['alert']['body'] 				= (string)$alert;
							$this->message['aps']['alert']['action-loc-key'] 	= (string)$actionlockey;
							break;
						case(empty($alert) && empty($actionlockey) && !empty($lockey) && !empty($locargs)):
							if(!is_string($lockey)) $this->_triggerError('Invalid Loc Key Format. See documentation for correct procedure.', E_USER_ERROR);
							$this->message['aps']['alert']['loc-key'] 	= (string)$lockey;
							$this->message['aps']['alert']['loc-args'] 	= $locargs;
							break;
						default:
							$this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
							break;
					}
			}
			
		public function addMessageBadge($number=NULL)
			{
				if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
				if($number) 
					{
						if(isset($this->message['aps']['badge'])) $this->_triggerError('Message Badge has already been created. Overwriting with '.$number.'.');
						$this->message['aps']['badge'] = (int)$number;
					}
			}
		
		public function addMessageCustom($key=NULL, $value=NULL)
			{
				if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
				if(!empty($key) && !empty($value)) 
					{
						if(isset($this->message[$key]))
							{
								unset($this->message[$key]);
								$this->_triggerError('This same Custom Key already exists and has not been delivered. The previous values have been removed.');
							}
						if(!is_string($key)) $this->_triggerError('Invalid Key Format. Key must be a string. See documentation for correct procedure.', E_USER_ERROR);
						$this->message[$key] = $value;
					}
			}
		
		public function addMessageSound($sound=NULL)
			{
				if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
				if($sound) 
					{
						if(isset($this->message['aps']['sound'])) $this->_triggerError('Message Sound has already been created. Overwriting with '.$sound.'.');
						$this->message['aps']['sound'] = (string)$sound;
					}
			}	
		
		private function _jsonEncode($array=false)
			{
				if(is_null($array)) return 'null';
				if($array === false) return 'false';
				if($array === true) return 'true';
				if(is_scalar($array))
					{
						if(is_float($array))
							{
								return floatval(str_replace(",", ".", strval($array)));
							}
						if(is_string($array))
							{
								static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
								return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $array) . '"';
							}
						else return $array;
					}
				$isList = true;
				for($i=0, reset($array); $i<count($array); $i++, next($array))
					{
						if(key($array) !== $i)
							{
								$isList = false;
								break;
							}
					}
				$result = array();
				if($isList)
					{
						foreach($array as $v) $result[] = json_encode($v);
						return '[' . join(',', $result) . ']';
					}
				else 
					{
						foreach ($array as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
						return '{' . join(',', $result) . '}';
					}
			}
	}
?>
