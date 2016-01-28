<?php
	//appel à l'api trello / type = post,get,delete
	function api_call($url,$type=null,$datapost=null,$service=null)
	{
		$init = curl_init();

		if ($service == 'jira'){
			$id = "";
			$password = "";
			$jira_identifiant = $id.":".$password;
			curl_setopt($init,CURLOPT_USERPWD, $jira_identifiant);

		}		
	 	curl_setopt($init, CURLOPT_URL, $url); 
		curl_setopt($init,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($init, CURLOPT_SSL_VERIFYPEER, false);
		if($type != null):
			curl_setopt($init,CURLOPT_POST,TRUE);
			curl_setopt($init, CURLOPT_CUSTOMREQUEST, $type);
			curl_setopt($init,CURLOPT_POSTFIELDS,$datapost);
		endif;
		return	curl_exec($init);
		curl_close($init);
	}


	$url_api_jira = "http://localhost:8181/rest/api/latest/";
	$project_name = "";
	$board_id = "";

	//get trello token :
	//https://trello.com/1/authorize?key=THEAPPKEY&name=THENAMEAPP&expiration=never&response_type=token&scope=read,write

	$token_trello = "";
	$key_trello ="";
	//Toutes les issues qui ont une version et dont cette version n'est pas release
	$url_jira = $url_api_jira."search?jql=project%20%3D%20".$project_name."%20AND%20fixVersion%20in%20unreleasedVersions()&fields=summary,issuetype,status,versions&maxResults=3";
	
	//$url_all_jira = $url_api_jira."search?jql=project%20%3D%20".$project_name."%20AND%20fixVersion%20in%20unreleasedVersions()&fields=summary,issuetype,status,versions&maxResults=3";
	
	// Version existante du projet
	$url_version_jira = $url_api_jira."project/".$project_name."/versions";   
   	$url_trello_list_new = "https://api.trello.com/1/boards/".$board_id."/lists?key=".$key_trello."&token=".$token_trello;
   	$url_trello_card_board = "https://api.trello.com/1/boards/".$board_id."/cards?key=".$key_trello."&token=".$token_trello;
   
   	// Recupere les lists existante sur le board Trello et contruit un tableau
   	$current_list = array();
	$content_l = api_call($url_trello_list_new);
	$content_lt = json_decode($content_l);
	foreach ($content_lt as $list) {
		$name_number_version = explode(']', $list->name);
		$current_list[substr($name_number_version[0], 1)] = $list->id;
	}
   	// recupere toutes les version du projet decashop dans jira 
    $content = api_call($url_version_jira,null,null,'jira');
    $content_jv = json_decode($content);
	$versions_unrelease = array();

	//construit un tableau avec les versions unrelease uniquement
    foreach ($content_jv as $version) {
 		if ($version->released == false):
 			$versions_unrelease[] = $version->name;	
 		endif;
 	}

 	// pour toutes les listes existantes dans le board Trello : supprime toutes les cards
 	foreach ($current_list as $list):
 		/*archive all card of a list*/
 		// $archive_card = "https://api.trello.com/1/lists/".$list."/archiveAllCards?key=&token=".$token_trello;
 		// $a = get_execute_trello_api($archive_card,'POST','');
 		
 		$all_card = api_call($url_trello_card_board);
 		$all_card_json = json_decode($all_card);
 		foreach ($all_card_json as $card) {
 			$delete_card =  "https://api.trello.com/1/cards/".$card->id."?key=".$key_trello."&token=".$token_trello;
 			$delete_all_card = api_call($delete_card,'DELETE','');
 		}
 	endforeach;
 	//Ajout des card, 1 par jira
 	foreach ($versions_unrelease as $version_unrelease):
 		// recupere pour une version donnée, toutes les jiras existantes		
	 	$jira_specific_version = $url_api_jira."search/?jql=project=".$project_name."%20AND%20fixVersion=".$version_unrelease."&fields=summary,issuetype,status,fixVersions,description&maxResults=50&lang=en";
	 	$content_i = api_call($jira_specific_version,null,null,'jira');
	    $content_jvi = json_decode($content_i);
	    if(!empty($content_jvi->issues) && isset($content_jvi->issues)):
			foreach ($content_jvi->issues as $jira) {

				if(isset($jira->fields->fixVersions[0]->name)&&isset($current_list[$jira->fields->fixVersions[0]->name])):
					//Ajout d'une carte dans la liste specifiée.
					$jira_specific_list_create_card = "https://api.trello.com/1/lists/".$current_list[$jira->fields->fixVersions[0]->name]."/cards?key=".$key_trello."&token=".$token_trello;
					//Titre de la carte : numero et titre
					$data = "name=".$jira->key.' : '.$jira->fields->summary;

					// si bug alors label rouge, si evol label bleu
					$data .= "&desc=".$jira->fields->description;
					var_dump($jira->fields);
					if ($jira->fields->issuetype->name == "Bug"){
						$data .= "&labels=red";
					}
					elseif ($jira->fields->issuetype->name == "Improvement") {
						$data .= "&labels=blue";
					}

					//Si la jira est fermé et donc deployé alors label vert
					if($jira->fields->status->name == 'Closed')
					{
						$data .= "&labels=green";
					}

					
					$abc =api_call($jira_specific_list_create_card,'POST',$data);
				endif;
			}
		endif;
		
 	endforeach;

 	echo 'Trello mis a jour';

?>
