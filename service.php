<?php

if(isset($_REQUEST)){
	include_once './dbfunctions.php';
	$dbFunctions = new DBFunctions();
	switch ($_REQUEST['action']) {
		case 'signUp':
			echo $dbFunctions->addUser($_REQUEST['nomeCliente'], $_REQUEST['emailCliente'], $_REQUEST['senhaCliente'], $_REQUEST['contatoCliente']);
			break;
		case 'updateUser':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoCliente']);
			$error = false;
			$msgError = '';
			if(isset($_REQUEST['fotoPerfil'])){

				$urlFotoAtual = $dbFunctions->getProfilePictureLink($_REQUEST['codigoCliente']);

				if(is_null($urlFotoAtual) === FALSE){

					$urlFotoAtual = str_replace("http://findt-it.gear.host/", "", $urlFotoAtual);
					unlink($urlFotoAtual);
					
				}	

			    $img = imagecreatefromstring(base64_decode($_REQUEST['fotoPerfil']));
				$dst = "upload/" . md5($_REQUEST['codigoCliente'] . date("Y-m-d H:i:s")) . ".jpg"; 

				file_put_contents($dst,base64_decode($_REQUEST['fotoPerfil']));
				
				$img_info = getimagesize($dst);
				
				$width = $img_info[0];
				$height = $img_info[1];
				
				 $src = imagecreatefromjpeg($dst);
				  
				if($width >= $height && $width > 400 || $width < $height && $height > 400 ){

					if($width >= $height){
						$new_width = 400;
						$new_height = round((400 * $height)/$width);
					} else {
						$new_height = 400;
						$new_width = round((400 * $width)/$height);
					}
					$tmp = imagecreatetruecolor($new_width, $new_height);
					imagecopyresampled($tmp, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

				} else {

					$tmp = imagecreatetruecolor($width, $height);
					imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $width, $height);

				}

				imagejpeg($tmp, $dst);
				if(($dbFunctions->updateUserAndProfilePicture($_REQUEST['codigoCliente'], $_REQUEST['nomeCliente'], $_REQUEST['emailCliente'], $_REQUEST['senhaCliente'], $_REQUEST['contatoCliente'], "http://findt-it.gear.host/". $dst)) === FALSE){
					$error = true;
					$msgError = "Não foi possível atualizar o seu perfil";
				}
				
			} else {
				if(($dbFunctions->updateUser($_REQUEST['codigoCliente'], $_REQUEST['nomeCliente'], $_REQUEST['emailCliente'], $_REQUEST['senhaCliente'], $_REQUEST['contatoCliente'])) === FALSE){
					$error = true;
					$msgError = "Não foi possível atualizar o seu perfil";
				}
			}

			if($error){
				$response['error'] = true;
         		$response['message'] = utf8_encode($msgError);         		
			} else {
				$response['error'] = false;
         		$response['message'] = utf8_encode('Perfil atualizado com sucesso!');
         		$response['client'] = $dbFunctions->getUserById($_REQUEST['codigoCliente']);
			}

			echo json_encode($response);

			break;
		case 'login':
			echo $dbFunctions->login($_REQUEST['emailCliente'], $_REQUEST['senhaCliente']);
			break;
		case 'insertItem':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoCliente']);
			$query = $dbFunctions->insertItem($_REQUEST['nomeItem'], $_REQUEST['descricaoItem'], $_REQUEST['latitudeItem'], $_REQUEST['longitudeItem'], $_REQUEST['raioItem'], $_REQUEST['nomeCategoria'], $_REQUEST['nomeStatus'], $_REQUEST['codigoCliente']);

			if($query === TRUE){
				$codigoItem = $dbFunctions->getItemId($_REQUEST['nomeItem'], $_REQUEST['descricaoItem'], $_REQUEST['nomeCategoria'], $_REQUEST['nomeStatus'], $_REQUEST['codigoCliente']);
				$error = false;
				$msgError = '';
				
				$i = 0;

				if(isset($_REQUEST['fotoItem'])){

					foreach ($_REQUEST['fotoItem'] as $key => $value) {

						$img = imagecreatefromstring(base64_decode($value));
						$dst = "upload/" . md5($codigoItem . $i . date("Y-m-d H:i:s")) . ".jpg"; 

						file_put_contents($dst,base64_decode($value));
						
						$img_info = getimagesize($dst);
						
						$width = $img_info[0];
						$height = $img_info[1];
						
						 $src = imagecreatefromjpeg($dst);
						  
						if($width >= $height && $width > 400 || $width < $height && $height > 400 ){

							if($width >= $height){
								$new_width = 400;
								$new_height = round((400 * $height)/$width);
							} else {
								$new_height = 400;
								$new_width = round((400 * $width)/$height);
							}
							$tmp = imagecreatetruecolor($new_width, $new_height);
							imagecopyresampled($tmp, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

						} else {

							$tmp = imagecreatetruecolor($width, $height);
							imagecopyresampled($tmp, $src, 0, 0, 0, 0, $width, $height, $width, $height);

						}

						imagejpeg($tmp, $dst);
						if($dbFunctions->insertPictureItem($codigoItem, "http://findt-it.gear.host/". $dst) === FALSE){
							$error = true;
							$msgError .= "Não foi possível salvar no banco de dados o arquivo \"".$dst."\"\n";
						}

						$i = $i + 1;

					} 

				} if($error){
					$response['error'] = true;
             		$response['message'] = utf8_encode($msgError);
				} else {
					$response['error'] = false;
             		$response['message'] = utf8_encode('Item cadastrado com sucesso!');

             		$query = strtr(
             					strtolower(
             						trim(
             							utf8_decode($_REQUEST['nomeItem']))), 
             					utf8_decode(
             						'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
             					 	'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');

					$palavras = explode(' ', $query);

					$aux = $dbFunctions->getSizeAndCountOfDescriptionsItem();

					$tamanhoDocumentos = $aux['tamanho'];
					$quantDocumentos = $aux['quantidade'];
					$documentos = array();
					$k1 = 1;
					$b = 0.75;

					for ($i=0; $i < count($palavras); $i++) {

						$indice = $dbFunctions->getInfoDescription(trim($palavras[$i]), 
													$_REQUEST['latitudeItem'], $_REQUEST['longitudeItem'],
													$_REQUEST['raioItem'], $_REQUEST['nomeCategoria'],
													$_REQUEST['nomeStatus'], $_REQUEST['codigoCliente']);

						if(strlen(trim($palavras[$i])) > 0){
							for ($j=0; $j < count($indice); $j++) {
								$documentos[$palavras[$i]][] = $indice[$j];				
							}			
						}

					}	

					if(count($documentos) > 0) {

						$similaridade = array();
						for ($i=0; $i < count($palavras); $i++) {

							if(strlen(trim($palavras[$i])) > 0){

								for ($j=0; $j < count($documentos[$palavras[$i]]); $j++) {	

									if (isset($similaridade[$documentos[$palavras[$i]][$j]['codigoItem']])) {

										$similaridade[$documentos[$palavras[$i]][$j]['codigoItem']] += ((($k1 + 1) * intval($documentos[$palavras[$i]][$j]['frequencia']))/(($k1 * ((1 - $b) + ($b * (intval($documentos[$palavras[$i]][$j]['tamanho'])/($tamanhoDocumentos / ($tamanhoDocumentos / $quantDocumentos)))))) + intval($documentos[$palavras[$i]][$j]['frequencia'])) * ( log((($quantDocumentos - count($documentos[$palavras[$i]]) + 0.5)/(count($documentos[$palavras[$i]]) + 0.5)), 2)));

									}
									else{

										$similaridade[$documentos[$palavras[$i]][$j]['codigoItem']] = ((($k1 + 1) * intval($documentos[$palavras[$i]][$j]['frequencia']))/(($k1 * ((1 - $b) + ($b * (intval($documentos[$palavras[$i]][$j]['tamanho'])/($tamanhoDocumentos / ($tamanhoDocumentos / $quantDocumentos)))))) + intval($documentos[$palavras[$i]][$j]['frequencia'])) * ( log((($quantDocumentos - count($documentos[$palavras[$i]]) + 0.5)/(count($documentos[$palavras[$i]]) + 0.5)), 2)));

									}
								}		
							}
						}

						arsort($similaridade);

						if(strcmp($_REQUEST['nomeStatus'],"Perdido") == 0){
							if($dbFunctions->addMatchItem($codigoItem, key($similaridade)) === FALSE){
								$response['error'] = true;
					     		$response['message'] = utf8_encode("Erro ao cadastrar Match de Item");
							} else {
								$response['error'] = false;
							}
						} else {
							if($dbFunctions->addMatchItem(key($similaridade), $codigoItem) === FALSE){
								$response['error'] = true;
					     		$response['message'] = utf8_encode("Erro ao cadastrar Match de Item");
							} else {
								$response['error'] = false;
							}
						}						

					}				
				}

			} else {
				$response['error'] = true;
             	$response['message'] = utf8_encode('Não foi possível cadastrar o Item!');
			}

			echo json_encode($response);
			break;
		case 'loadItems':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoCliente']);
			echo json_encode (
					array("hasNotReadNotification" => 
						$dbFunctions->hasNotReadNotification($_REQUEST['codigoCliente']),
				 		"listItem" => 
				 		$dbFunctions->listItem(
				 			$_REQUEST['codigoCliente'],
				 			$_REQUEST['complementoQuery'])));
			break;
		case 'getListMatch':
			if($dbFunctions->hasMatchNotNotificate($_REQUEST['codigoCliente'])){
				echo $dbFunctions->listMatchItemsOfUser($_REQUEST['codigoCliente']);
				$dbFunctions->addNotificateMatchItemFound($_REQUEST['codigoCliente']);
				$dbFunctions->addNotificateMatchItemLost($_REQUEST['codigoCliente']);
			} else {
				echo json_encode(array());
			}
			break;
		case 'getListConversation':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoCliente']);
			echo $dbFunctions->listConversation($_REQUEST['codigoCliente']);
			break;
		case 'addVisualizationMatch':
			$dbFunctions->addVisualizationMatchItemLost(
				$_REQUEST['codigoClienteRemetente'],
				$_REQUEST['codigoClienteDestinatario']);
			$dbFunctions->addVisualizationMatchItemFound(
				$_REQUEST['codigoClienteRemetente'], 
				$_REQUEST['codigoClienteDestinatario']);
			break;
		case 'getMessages':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoClienteRemetente']);
			echo json_encode (
				array("messages" => $dbFunctions->getMessages(
				$_REQUEST['codigoClienteRemetente'],
				$_REQUEST['codigoClienteDestinatario'], 
				$_REQUEST['contadorMensagens']),
			 		"lastActivityOfUser" => $dbFunctions->getLastActivityOfUser(
			 			$_REQUEST['codigoClienteDestinatario'])));
			break;
		case 'getNewMessages':
			echo json_encode($dbFunctions->getNewsMessages($_REQUEST['codigoCliente']));
			break;
		case 'postMessage':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoClienteRemetente']);
			$dbFunctions->postMessage(
				$_REQUEST['codigoClienteRemetente'],
				$_REQUEST['codigoClienteDestinatario'],
				$_REQUEST['conteudoMensagem']);
			break;
		case 'addVisualizationMessages':
			$dbFunctions->updateLastActivityOfUser($_REQUEST['codigoClienteRemetente']);
			$dbFunctions->addVisualizationMessages($_REQUEST['codigoClienteDestinatario'], $_REQUEST['codigoClienteRemetente']);
			break;
		default:
			$response['error'] = true;
     		$response['message'] = utf8_encode("Opção inválida");
     		echo json_encode($response);
			break;
	}


} else {
	$response['error'] = true;
	$response['message'] = utf8_encode("Opção inválida");
	echo json_encode($response);
}

?>