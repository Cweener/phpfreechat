<?php
/**
 * phpfreechat.class.php
 *
 * Copyright � 2006 Stephane Gully <stephane.gully@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, 
 * but WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details. 
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the
 * Free Software Foundation, 51 Franklin St, Fifth Floor,
 * Boston, MA  02110-1301  USA
 */

require_once dirname(__FILE__)."/phpfreechatconfig.class.php";
if (!class_exists("xajax"))
  require_once dirname(__FILE__)."/../lib/xajax_0.2_stable/xajax.inc.php";
require_once dirname(__FILE__)."/../debug/log.php";

/**
 * phpFreeChat is the entry point for developpers
 *
 * @example ../demo/demo1_simple.php
 * @author Stephane Gully <stephane.gully@gmail.com>
 */
class phpFreeChat
{
  var $chatconfig;
  var $xajax;
  
  function phpFreeChat( $params = array() )
  {
    // start the session : session is used for locking purpose and cache purpose
    if(session_id() == "") session_start();
    if (isset($_GET["init"])) session_destroy();

    $params["sessionid"] = session_id();
    
    $c =& phpFreeChatConfig::Instance( $params );
    
    // Xajax doesn't support yet static class methode call
    // I use basic functions to wrap to my statics methodes
    function handleRequest($request, $shownotice = true)
      {
        $c =& phpFreeChatConfig::Instance();
        $c->shownotice = $shownotice;
        return phpFreeChat::HandleRequest($request);
      }
    // then init xajax engine
    $this->xajax = new xajax($c->server_file, $c->prefix);
    //$this->xajax->debugOn();
    $this->xajax->registerFunction("handleRequest");
    $this->xajax->processRequests();
  }

  /**
   * printJavaScript must be called into html header
   * usage:
   * <code>
   *   <?php $chat->printJavascript(); ?>
   * </code>
   */
  function printJavaScript()
  {
    $c =& phpFreeChatConfig::Instance();
    
    $this->xajax->printJavascript("../lib/xajax_0.2_stable/");
    
    if (!class_exists("Smarty")) require_once dirname(__FILE__)."/../lib/Smarty-2.6.7/libs/Smarty.class.php";
    $smarty = new Smarty();
    $smarty->left_delimiter  = "~[";
    $smarty->right_delimiter = "]~";
    $smarty->template_dir    = dirname(__FILE__).'/../templates/';
    $smarty->compile_dir     = $c->data_private."/templates_c/";    
    $smarty->compile_check   = true;
    $smarty->debugging       = false;
    $c->assignToSmarty($smarty);
    
    echo "<script type=\"text/javascript\">\n<!--\n";
    $smarty->display("javascript1.js.tpl");
    //echo $this->xajax->compressJavascript($js);
    echo "\n-->\n</script>\n";
  }

  /**
   * printChat must be called somewhere in the page
   * it inserts necessary html which will receive chat's data
   * usage:
   * <code>
   *   <?php $chat->printChat(); ?>
   * </code>
   */
  function printChat()
  {
    $c =& phpFreeChatConfig::Instance();
    
    if (!class_exists("Smarty")) require_once dirname(__FILE__)."/../lib/Smarty-2.6.7/libs/Smarty.class.php";
    $smarty = new Smarty();
    $smarty->left_delimiter  = "~[";
    $smarty->right_delimiter = "]~";
    $smarty->template_dir    = dirname(__FILE__).'/../templates/';
    $smarty->compile_dir     = $c->data_private."/templates_c/";    
    $smarty->compile_check   = true;
    $smarty->debugging       = false;
    $c->assignToSmarty($smarty);
    
    $smarty->display("chat.html.tpl");
  }
  
  /**
   * printStyle must be called in the header
   * it inserts CSS in order to style the chat
   * usage:
   * <code>
   *   <?php $chat->printStyle(); ?>
   * </code>
   */
  function printStyle()
  {
    $c =& phpFreeChatConfig::Instance();
    
    if (!class_exists("Smarty")) require_once dirname(__FILE__)."/../lib/Smarty-2.6.7/libs/Smarty.class.php";
    $smarty = new Smarty();
    $smarty->left_delimiter  = "~[";
    $smarty->right_delimiter = "]~";
    $smarty->template_dir    = dirname(__FILE__).'/../templates/';
    $smarty->compile_dir     = $c->data_private."/templates_c/";
    $smarty->compile_check   = true;
    $smarty->debugging       = false;
    $c->assignToSmarty($smarty);
    
    echo "<style type=\"text/css\">\n<!--\n";
    $smarty->display("style.css.tpl");
    if ($c->css_file)
      $smarty->display($c->css_file);
    echo "\n-->\n</style>\n";
  }
  
  function FilterNickname($nickname)
  {
    $c =& phpFreeChatConfig::Instance();
    $nickname = substr($nickname, 0, $c->max_nick_len);
    $nickname = htmlspecialchars(stripslashes($nickname));
    return $nickname;
  }
  
  // search/replace smileys
  function FilterSmiley($msg)
  {
    $c =& phpFreeChatConfig::Instance();
    // build a preg_replace array
    $search = array();
    $replace = array();
    $query = "/(";
    foreach($c->smileys as $s_file => $s_strs)
    {
      foreach ($s_strs as $s_str)
      {
	$query .= preg_quote($s_str)."|";
	$search[] = "/".preg_quote($s_str)."/";
	$replace[] = '<img src="'.$s_file.'" alt="'.$s_str.'" />';
      }
    }
    $query = substr($query, 0, strlen($query)-1);
    $query .= ")/i";

    $split_words = preg_split($query, $msg, -1, PREG_SPLIT_DELIM_CAPTURE);
    $msg = "";
    foreach($split_words as $word)
      $msg .= preg_replace($search, $replace, $word);
    return $msg;
  }

  
  function FilterMsg($msg)
  {
    $c =& phpFreeChatConfig::Instance();
    $msg = substr($msg, 0, $c->max_text_len);
    $msg  = htmlspecialchars(stripslashes($msg));
    
    $msg = phpFreeChat::FilterSmiley($msg);

    if ($msg[0] == "\n") $msg = substr($msg, 1); // delete the first \n generated by FF
    if (strpos($msg,"\n") > 0) $msg  = "<br/>".$msg;
    $msg = str_replace("\r\n", "<br/>", $msg);
    $msg = str_replace("\n", "<br/>", $msg);
    $msg = str_replace("\t", "    ", $msg);
    $msg = str_replace("  ", "&nbsp;&nbsp;", $msg);
    $msg = preg_replace('/('.preg_quote(phpFreeChat::FilterNickname($c->nick)).')/i',  "<strong>$1</strong>", $msg );
    $msg = preg_replace('/(http\:\/\/[^\s]*)/i',  "<a href=\"$1\">$1</a>", $msg );
    return $msg;
  }
    
  function HandleRequest($request)
  {
    $xml_reponse = new xajaxResponse();
    $request = stripslashes($request);

    if (preg_match("/^\/([a-z]*)( (.*)|)/i", $request, $res))
    {
      $cmd   = "Cmd_".$res[1];
      $param = $res[3];
      // call the command
      phpFreeChat::$cmd($xml_reponse, $param);
    }
    else
    {
      // by default this is a simple send command
      $cmd   = "Cmd_send";
      // call the command
      phpFreeChat::$cmd($xml_reponse, $request);
    } 
    
    return $xml_reponse->getXML();
  }
  
  function Cmd_update(&$xml_reponse)
  {
    $c =& phpFreeChatConfig::Instance();
    phpFreeChat::Cmd_updateMyNick($xml_reponse);
    phpFreeChat::Cmd_getOnlineNick($xml_reponse);
    phpFreeChat::Cmd_getNewMsg($xml_reponse);
    $xml_reponse->addScript($c->prefix."timeout_var = window.setTimeout('".$c->prefix."handleRequest(\\'/update\\')', ".$c->refresh_delay.");");
  }
  
  function Cmd_connect(&$xml_reponse)
  {
    $c =& phpFreeChatConfig::Instance();
    $_SESSION[$c->prefix."from_id_".$c->id] = 0;
    $xml_reponse->addScript("var ".$c->prefix."timeout_var;");
    $xml_reponse->addScript("var ".$c->prefix."nicklist = Array();");

    // check if the wanted nickname was allready known
    $container =& $c->getContainerInstance();
    $nickid = $container->getNickId($c->nick);
    if ($nickid == "undefined") // is nickname unused ?
      phpFreeChat::Cmd_notice($xml_reponse, htmlspecialchars(stripslashes($c->nick))." is connected");

    if ($c->debug) pxlog("Cmd_connect[".$c->sessionid."]: nick=".$c->nick." nickid=".$nickid, "chat", $c->id);

    if ($c->nick == "")
      // ask user to choose a nickname
      phpFreeChat::Cmd_asknick($xml_reponse, "");
    else
      phpFreeChat::Cmd_nick(&$xml_reponse, $c->nick);

    phpFreeChat::Cmd_update($xml_reponse);
  }

  function Cmd_asknick(&$xml_reponse, $nicktochange)
  {
    $c =& phpFreeChatConfig::Instance();
    if ($c->frozen_nick)
    {
      // assign a random nick
      phpFreeChat::Cmd_nick($xml_reponse, $nicktochange."".rand(1,1000));
    }
    else
    {
      if ($nicktochange == "")
        $msg = "Please enter your nickname";
      else
        $msg = "'".$nicktochange."' is used, please choose another nickname.";
      $xml_reponse->addScript("var newpseudo = prompt('".addslashes($msg)."', '".addslashes($nicktochange)."'); ".$c->prefix."handleRequest('/nick ' + newpseudo);");
    }
  }

  function Cmd_nick(&$xml_reponse, $newnick)
  {
    $c =& phpFreeChatConfig::Instance();
    $container =& $c->getContainerInstance();
    $newnickid = $container->getNickId($newnick);

    if ($newnickid == "undefined")
    {
      // this is a real nickname change
      $container->changeNick($newnick, $c->sessionid);
      $oldnick = $c->nick;
      $c->nick = $newnick;
      $c->saveInSession();
      $xml_reponse->addAssign($c->prefix."handle", "value", $newnick);
      $xml_reponse->addScript("document.getElementById('".$c->prefix."words').focus();");
      if ($oldnick != $newnick && $oldnick != "")
	phpFreeChat::Cmd_notice($xml_reponse, htmlspecialchars(stripslashes($oldnick))." changes his nickname to ".htmlspecialchars(stripslashes($newnick)));
      if ($c->debug) pxlog("Cmd_nick[".$c->sessionid."]: first time nick is assigned -> newnick=".$c->nick, "chat", $c->id);
    }
    else if ($newnickid == $c->sessionid)
    {
      // user didn't change his nickname
      $xml_reponse->addAssign($c->prefix."handle", "value", $newnick);
      $xml_reponse->addScript("document.getElementById('".$c->prefix."words').focus();");
      if ($c->debug) pxlog("Cmd_nick[".$c->sessionid."]: user just reloded the page so let him keep his nickname without any warnings -> nickid=".$newnickid." nick=".$newnick, "chat", $c->id);
    }
    else
    {
      // the wanted nick is allready used
      if ($c->debug) pxlog("Cmd_nick[".$c->sessionid."]: wanted nick is allready in use -> wantednickid=".$newnickid." wantednick=".$newnick, "chat", $c->id);
      phpFreeChat::Cmd_asknick($xml_reponse, $newnick);
    }
  }

  function Cmd_notice(&$xml_reponse, $msg)
  {
    $c =& phpFreeChatConfig::Instance();
    if ($c->shownotice)
    {
      $container =& $c->getContainerInstance();
      $container->writeMsg("*notice*", $msg);
      phpFreeChat::Cmd_getNewMsg($xml_reponse);
    }
  }

  function Cmd_me(&$xml_reponse, $msg)
  {
    $c =& phpFreeChatConfig::Instance();
    $container =& $c->getContainerInstance();
    $container->writeMsg("*me*", $c->nick." ".$msg);
    phpFreeChat::Cmd_getNewMsg($xml_reponse);    
  }
  
  function Cmd_quit(&$xml_reponse)
  {
    $c =& phpFreeChatConfig::Instance();
    $container =& $c->getContainerInstance();
    if ($container->removeNick($c->nick))
      phpFreeChat::Cmd_notice($xml_reponse, $c->nick." quit");
    else
      phpFreeChat::Cmd_notice($xml_reponse, "error: ".$c->nick." can't quit");
    if ($c->debug) pxlog("Cmd_quit[".$c->sessionid."]: a user just quit -> nick=".$c->nick, "chat", $c->id);
  }
  
  function Cmd_getOnlineNick(&$xml_reponse)
  {
    $c =& phpFreeChatConfig::Instance();

    // get the actual nicklist
    $oldnicklist = $_SESSION[$c->prefix."nicklist_".$c->id];

    $container =& $c->getContainerInstance();
    $disconnected_users = $container->removeObsoletNick();
    foreach ($disconnected_users as $u)
      phpFreeChat::Cmd_notice($xml_reponse, $u." disconnected (timeout)");
    $users = $container->getOnlineNick();
    sort($users);
    $html = '<ul>';
    $js = "";
    foreach ($users as $u)
    {
      $nickname = htmlspecialchars(stripslashes($u));
      $html    .= '<li>'.$nickname.'</li>';
      $js      .= "'".$nickname."',";
    }
    $html .= '</ul>';
    $js    = substr($js, 0, strlen($js)-1); // remove last ','
      
    $xml_reponse->addAssign($c->prefix."online", "innerHTML", $html);
    $xml_reponse->addScript($c->prefix."nicklist = Array(".$js.");");
  }

  function Cmd_updateMyNick(&$xml_reponse)
  {
    $c =& phpFreeChatConfig::Instance();
    $container =& $c->getContainerInstance();
    $ok = $container->updateNick($c->nick);
    if (!$ok)
      phpFreeChat::Cmd_error(&$xml_reponse, "Cmd_updateMyNick failed");
  }
  
  function Cmd_getNewMsg(&$xml_reponse)
  {
    // get params from config obj
    $c =& phpFreeChatConfig::Instance();
    
    // check this methode is not being called
    if( isset($_SESSION[$c->prefix."lock_readnewmsg_".$c->id]) )
    {
      // kill the lock if it has been created more than 10 seconds ago
      $last_10sec = time()-10;
      $last_lock = $_SESSION[$c->prefix."lock_readnewmsg_".$c->id];
      if ($last_lock < $last_10sec) $_SESSION[$c->prefix."lock_".$c->id] = 0;
      if ( $_SESSION[$c->prefix."lock_readnewmsg_".$c->id] != 0 ) exit;
    }

    // create a new lock
    $_SESSION[$c->prefix."lock_readnewmsg_".$c->id] = time();
    
    $from_id = $_SESSION[$c->prefix."from_id_".$c->id];
    
    $container =& $c->getContainerInstance();
    $new_msg = $container->readNewMsg($from_id);
    $new_from_id = $new_msg["new_from_id"];
    $messages    = $new_msg["messages"];

    // transform new message in html format
    $html = '';
    foreach ($messages as $msg)
    {
      $cmd_type = "cmd_msg";
      if (preg_match("/\*([a-z]*)\*/i", $msg[3], $res))
      {
	if ($res[1] == "notice")
	  $cmd_type = "cmd_notice";
	else if ($res[1] == "me")
	  $cmd_type = "cmd_me";
      }
      $html .= '<div id="'.$c->prefix.'msg'.$msg[0].'" class="'.$c->prefix.$cmd_type.' '.$c->prefix.'message'.($from_id == 0 ? " ".$c->prefix."oldmsg" : "").'">';
      $html .= '<span class="'.$c->prefix.'date'.((isset($msg[1]) && date("d/m/Y") == $msg[1]) ? " ".$c->prefix."invisible" : "" ).'">'.(isset($msg[1]) ? $msg[1] : "").'</span> ';
      $html .= '<span class="'.$c->prefix.'heure">'.(isset($msg[2]) ? $msg[2] : "").'</span> ';
      if ($cmd_type == "cmd_msg")
      {
	$html .= '<span class="'.$c->prefix.'pseudo">&lt;'.(isset($msg[3]) ? $msg[3] : "").'&gt;</span> ';
	$html .= '<span class="'.$c->prefix.'words">'.(isset($msg[4]) ? $msg[4] : "").'</span>';
      }
      else if ($cmd_type == "cmd_notice" || $cmd_type == "cmd_me")
      {
	$html .= '<span class="'.$c->prefix.'words">* '.(isset($msg[4]) ? $msg[4] : "").'</span>';
      }
      $html .= '</div>';
    }
  	
    if ($html != "") // do not send anything if there is no new messages to show
    {
      // store the new msg id
      $_SESSION[$c->prefix."from_id_".$c->id] = $new_from_id;
      // append new messages to chat zone
      $xml_reponse->addAppend($c->prefix."chat", "innerHTML", $html);
      // move the scrollbar from N line down
      $xml_reponse->addScript('var div_msg; var msg_height = 0;');
      foreach ($messages as $msg)
        $xml_reponse->addScript('div_msg = document.getElementById(\''.$c->prefix.'msg'.$msg[0].'\'); msg_height += div_msg.offsetHeight+2;');
      $xml_reponse->addScript('document.getElementById(\''.$c->prefix.'chat\').scrollTop += msg_height;');
    }

    // remove the lock
    $_SESSION[$c->prefix."lock_readnewmsg_".$c->id] = 0;
  }
  
  function Cmd_send(&$xml_reponse, $msg)
  {
    $c =& phpFreeChatConfig::Instance();
        
    // check the nick is not allready known
    $nick = phpFreeChat::FilterNickname($c->nick);
    $text = phpFreeChat::FilterMsg($msg);
        
    $errors = array();
    if ($text == "") $errors[$c->prefix."words"] = "Text cannot be empty.";
    if ($nick == "") $errors[$c->prefix."handle"] = "Please enter your nickname.";
    if (count($errors) == 0)
    {
      $container =& $c->getContainerInstance();
      $container->writeMsg($nick, $text);
      if ($c->debug) pxlog("Cmd_send[".$c->sessionid."]: a user just sent a message -> nick=".$c->nick." m=".$text, "chat", $c->id);
    	
      // a message has been posted so :
      // - read new messages
      // - give focus to "words" field
      $xml_reponse->addScript($c->prefix."ClearError(Array('".$c->prefix."words"."','".$c->prefix."handle"."'));");
      $xml_reponse->addScript("document.getElementById('".$c->prefix."words').focus();");
      phpFreeChat::Cmd_getNewMsg($xml_reponse);
    }
    else
    {
      // an error occured, just ignore the message and display errors
      foreach($errors as $e)
        if ($c->debug) pxlog("Cmd_send[".$c->sessionid."]: user can't send a message -> nick=".$c->nick." err=".$e, "chat", $c->id);
      phpFreeChat::Cmd_error($xml_reponse, $errors);
      if (isset($errors[$c->prefix."handle"])) // the nick is empty so give it focus
        $xml_reponse->addScript("document.getElementById('".$c->prefix."handle').focus();");
    }
  }
  
  function Cmd_join(&$xml_reponse, $newchat)
  {
    $c =& phpFreeChatConfig::Instance();
  }
  
  function Cmd_error(&$xml_reponse, $errors)
  {
    $c =& phpFreeChatConfig::Instance();
    if (is_array($errors))
    {
      $error_ids = ""; $error_str = "";
      foreach ($errors as $k => $e) { $error_ids .= ",'".$k."'"; $error_str.= $e." "; }
      $error_ids = substr($error_ids,1);
      $xml_reponse->addScript($c->prefix."SetError('".addslashes(stripslashes($error_str))."', Array(".$error_ids."));");
    }
    else
      $xml_reponse->addScript($c->prefix."SetError('".addslashes(stripslashes($errors))."', Array());");
  }  
}

?>