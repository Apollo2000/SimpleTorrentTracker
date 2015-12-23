<?
// FUNCTION FILE

function sendError($code)
{
	$error = Array('failure code' => $code); 
	echo bencode($error); 
	exit();
}

// Get user real IP
function getUserIP() 
{
    if(isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) return ($_SERVER['REMOTE_ADDR']);
    elseif(isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) return ($_SERVER['HTTP_CLIENT_IP']);
    elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) return ($_SERVER['HTTP_X_FORWARDED_FOR']);
    else return 0;
}

// Encoding bencoded data (https://wiki.theory.org/Decoding_encoding_bencoded_data_with_PHP)
function bencode(&$d)
{
	if(is_array($d)){
		$ret="d";
		ksort($d, SORT_STRING);
		foreach($d as $key=>$value) {
			if(is_array($d)){
				// skip the isDct element, only if it's set by us
				if($key=="isDct") continue;
				$ret.=strlen($key).":".$key;
			}
			if (is_int($value) || is_float($value)){
				$ret.="i${value}e";
			}else if (is_string($value)) {
				$ret.=strlen($value).":".$value;
			} else {
				$ret.=bencode ($value);
			}
		}
		return $ret."e";
	} elseif (is_string($d)) // fallback if we're given a single bencoded string or int
		return strlen($d).":".$d;
	elseif (is_int($d) || is_float($d))
		return "i${d}e";
	else
		return null;
}
?>
