<?php
/****************************************************************************
 * cdr.php		- Model for CDR. Manages refresh from spooler.
 * version 		- 1.0.353
 * 
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 *
 * The Initial Developer of the Original Code is
 *   Louise Berthilson <louise@it46.se>
 *
 *
 ***************************************************************************/


class Cdr extends AppModel{

      var $name = 'Cdr';


	var $belongsTo = array('User'); 

	var $hasMany = array('MonitorIvr' => array(
                        	       'order' => 'MonitorIvr.id ASC',
                        	       'dependent' => true)
				       );


/*
 * Fetching new data from spooler
 *
 *
 */

	function refresh(){

	$mapping = Configure::read('EXT_MAPPING');


           //** Fetch CDR from spooler **//
      	      $array = Configure::read('cdr');
	      $obj = new ff_event($array);	       

	      if ($obj -> auth != true) {
    	       	  die(printf("Unable to authenticate\r\n"));
	      }

      	      while ($entry = $obj->getNext('update')){

	          $channel = $entry['Channel-Name'];
	      	  $channel_state = $entry['Channel-State'];
	      	  $answer_state = $entry['Answer-State'];
		  $call_id = $entry['Unique-ID'];
		  $application='';
		  $start='';
		  $ext = $entry['Caller-Destination-Number'];
		  $epoch = floor($entry['Event-Date-Timestamp']/1000000);

	       	  $this->set('epoch' , $epoch);
		  $this->set('channel_state' , $channel_state);
	       	  $this->set('call_id', $call_id);
		  $this->set('caller_name', urldecode($entry['Caller-Caller-ID-Name']));
    	       	  $this->set('caller_number',urldecode($entry['Caller-Caller-ID-Number']));
	       	  $this->set('extension', $ext);


		  //FIXME! //Create custom event for CS_ROUTING CDR
		  $ivr_parent = $this->query('select title from ivr_menus where parent = true');
	       	  $this->set('title', $ivr_parent[0]['ivr_menus']['title']);

		  //Determine sender protocol (skype,gsm,sip)
	 	  $proto = $this->getProto($channel);
       	          $this->set('proto',$proto);

		  foreach($mapping as $app => $reg){
		       preg_match($reg,$ext,$matches);
		         if($matches){	
	 	        	$application = $app;
		         }
		   }

		   //Determine whether entry should be stored or not
		   $insert = $this->insertCDR($proto,$channel_state,$answer_state);

		   //Calculate length of LAM and IVR calls

		     //LAM: fetch length of file from Messages
		     if ($application == 'lam' && $channel_state=='CS_ROUTING'){
		     	$this->bindModel(array('hasOne' => array(
		     					      	 'Message' => array(
								 'foreignKey' => false,
								 'conditions'=>array('Message.file'=>$call_id)))));
					

		        $message = $this->Message->findByFile($call_id);
		        $this->set('length',$message['Message']['length']);
		        $this->set('title', LAM_DEFAULT);
		     } 

		     //IVR: Epoch diff of CS_ROUTING and CS_DESTROY
		     elseif ($channel_state =='CS_DESTROY'){
		     	    if($start = $this->find('first', array('conditions'=>array('call_id'=>$call_id,'CHANNEL_STATE' =>'CS_ROUTING','application'=>'ivr')))){
		     	    	$length = $epoch - $start['Cdr']['epoch'];
		     		$this->set('length',$length);
		     		}
		     }

		     $this->set('application', $application);

		     if($insert){
		     
			$this->create($this->data);
	  	     	$this->save($this->data);

		        if($start){
		           $this->id = $start['Cdr']['id'];
		           $this->saveField('length',$length);
		        }
		   


		  //Add routing(start) and destroy(end) to monitor_ivr if application='Voice menu'
		  if($application =='ivr' || ($channel_state=='CS_DESTROY' && $this->MonitorIvr->find('count',array('conditions' => array('MonitorIvr.call_id' => $call_id))))){

		  	$epoch = floor($entry['Event-Date-Timestamp']/1000000);
	       	  	$this->MonitorIvr->set('epoch' , $epoch);
		  	$this->MonitorIvr->set('call_id' , $entry['Unique-ID']);
	       	  	$this->MonitorIvr->set('ivr_code', '');
		  	$this->MonitorIvr->set('digit', '');
    	       	  	$this->MonitorIvr->set('node_id','');
	       	  	$this->MonitorIvr->set('caller_number', urldecode($entry['Caller-Caller-ID-Number']));
	       	  	$this->MonitorIvr->set('extension', $entry['Caller-Destination-Number']);
		  	//$this->MonitorIvr->set('cdr_id', $cdr['Cdr']['id']);
		  	$this->MonitorIvr->set('type', $channel_state);

		  	$this->MonitorIvr->create($this->MonitorIvr->data);
	  	  	$this->MonitorIvr->save($this->MonitorIvr->data);

		  	$this->log("Channel state: ".$entry['Channel-State']."; Call-ID: ".$entry['Unique-ID']."; Timestamp: ".$entry['Event-Date-Timestamp'], "cdr"); 
		  	$this->log("Type: ".$entry['Channel-State']."; Call-ID: ".$entry['Unique-ID']."; Timestamp: ".$entry['Event-Date-Timestamp'], "monitor_ivr");

			//Check if user is registered

			//Process only CS_ROUTING (start) messages
			if($entry['Channel-State']=='CS_ROUTING'){

			$value = urldecode($entry['Caller-Caller-ID-Number']);
			$field = 'phone1';

		  	if( $proto == 'skype') { $field = 'skype';}
		  	elseif( $proto == 'gsm') { $field = 'phone1';}
			elseif( $proto == 'sip') { $field = 'phone1';}
			

			    if ($userData = $this->User->find('first',array('conditions' => array('User.'.$field => $value)))){ 

		 		$count_ivr = $userData['User']['count_ivr']+1;
				$id = 	$userData['User']['id'];
	 			$this->User->set(array('id' => $id, 'count_ivr'=>$count_ivr,'last_app'=>'ivr','last_epoch'=>time()));
				$this->User->id = $id;
 		 		$this->User->save($this->data);

		            } else {
			        $created = time();
		 	        $user =array($field => $value,'created'=> $created,'new'=>1,'count_ivr'=>1,'first_app'=>'ivr','first_epoch' => $created, 'last_app'=>'ivr','last_epoch'=>$created,'acl_id'=>1);
		     	        $this->User->create();
		     	        $this->User->save($user);
				
			    }
			}

		} 

		  } //insert into CDR		

	      }  //while

              //** Fetch MONITOR_IVR from spooler **//
      	      $array = Configure::read('monitor_ivr');
	      $obj = new ff_event($array);	       

	      if ($obj -> auth != true) {
    	       	  die(printf("Unable to authenticate\r\n"));
	      }

      	      while ($entry = $obj->getNext('update')){


		$cdr = $this->find('first', array('conditions' => array('call_id' => $entry['FF-IVR-Unique-ID'], 'channel_state'=>'CS_ROUTING'),'order' =>'Cdr.call_id'));


		  $epoch = floor($entry['Event-Date-Timestamp']/1000000);
	       	  $this->MonitorIvr->set('epoch' , $epoch);
		  $this->MonitorIvr->set('call_id' , $entry['FF-IVR-Unique-ID']);
	       	  $this->MonitorIvr->set('ivr_code', urldecode($entry['FF-IVR-IVR-Name']));
		  $this->MonitorIvr->set('digit', $entry['FF-IVR-IVR-Node-Digit']);
    	       	  $this->MonitorIvr->set('node_id',$entry['FF-IVR-IVR-Node-Unique-ID']);
	       	  $this->MonitorIvr->set('caller_number', urldecode($entry['FF-IVR-Caller-ID-Number']));
	       	  $this->MonitorIvr->set('extension', $entry['FF-IVR-Destination-Number']);
		  $this->MonitorIvr->set('cdr_id', $cdr['Cdr']['id']);
		  $this->MonitorIvr->set('type', __('tag',true));

		  $this->MonitorIvr->create($this->MonitorIvr->data);
	  	  $this->MonitorIvr->save($this->MonitorIvr->data);

		  //Save IVR title to CDR
		    
		  $this->id = $cdr['Cdr']['id'];
		  $this->saveField('title',urldecode($entry['FF-IVR-IVR-Name']));


		  //$this->log("Channel state: ".$entry['Channel-State']."; Call-ID: ".$entry['Unique-ID']."; Timestamp: ".$entry['Event-Date-Timestamp'], "cdr"); 

	      }



	}

/*
 * Get unique id for call
 *
 * @param int $id 
 *
 */

    function getCallId($id){

    $data = $this->findById($id);
    return $data['Cdr']['call_id'];     
    }



/*
 * Convert date array to epoch
 *
 * @param array $data
 * @return array $epoch
 */

   function dateToEpoch($data){
 
      	 $start	  = $data['start_time'];
      	 $end 	  = $data['end_time'];
 
	$hour_start = $start['hour'];
	$hour_end   = $end['hour'];

	if($start['meridian']=='pm' && $start['hour']!=12){ 
	   	$hour_start=$hour_start+12;
		} 
 	elseif($start['meridian']=='am' && $start['hour']==12){ 
	   	$hour_start='00';
	}

	if($end['meridian']=='pm' && $end['hour']!=12){ 
	   	$hour_end=$hour_end+12;
		} 
 	elseif($end['meridian']=='am' && $end['hour']==12){ 
	   	$hour_end='00';
	}

	 $start = $start['year'].'-'.$start['month'].'-'.$start['day'].' '.$hour_start.':'.$start['min'].':00';
     	 $end   = $end['year'].'-'.$end['month'].'-'.$end['day'].' '.$hour_end.':'.$end['min'].':00';


	 $epoch['start'] = strtotime($start);
      	 $epoch['end']  = strtotime($end);

	 return $epoch;

	 }


/*
 * Determine channel used for incoming call (Skype/SIP/GSM)
 *
 *
 */
	 function getProto($channel){

		  $proto=false;	

		  if(stripos($channel,'skypopen')===0){ 
		  	$proto = 'skype';
		  } elseif(stripos($channel,'gsmopen')===0){ 
		    	$proto = 'gsm';
		  } elseif(stripos($channel,'sofia')===0){ 
		        $proto = 'sip';
		  }
		  return $proto;
	  }



/*
 * Determine whether CDR should be saved or not
 *
 *
 */

	function insertCDR($proto,$channel_state,$answer_state){

		   $insert = true;
		   switch ($proto){

		    case 'gsm':
		    if ($channel_state == 'CS_ROUTING' && $answer_state =='answered'){
		         $insert = false;
		    } 
		    break;

		    case 'sip':
		    if ($channel_state == 'CS_ROUTING' && $answer_state =='answered'){
		         $insert = false;
		    }
		    break;
	

		    case 'skype':
		    if ($channel_state == 'CS_ROUTING' && $answer_state =='ringing'){
		         $insert = false;
		    }
		    break;
		   }

		   return $insert;
		   }

}

?>
