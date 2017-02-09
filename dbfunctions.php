<?php
 
class DBFunctions {
 
    private $db;
    private $conn;
 
    //put your code here
    // constructor
    function __construct() {
        include_once './dbconnect.php';
        // connecting to database
        $this->db = new DBConnect();
        $this->conn = $this->db->connect();
    }
 
    // destructor
    function __destruct() {         
    }
	
	public function isUserRegistered($email){
		$result = $this->conn->query("SELECT * from cliente where emailCliente='$email'");
		
		if ($result->num_rows > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	public function addUser($user_name, $user_email, $user_password, $user_contact){
        if(!$this->isUserRegistered($user_email)) {	             
    		if($this->conn->query("INSERT into cliente (nomeCliente, emailCliente, senhaCliente, contatoCliente) values ('$user_name', '$user_email', '$user_password','$user_contact')") === TRUE) {
                $response['error'] = false;
                $response['message'] = 'Cadastrado com sucesso'; 
            } else {
                $response['error'] = true;
                $response['message'] = $this->conn->error; 
            }
        } else {
            $response['error'] = true;
            $response['message'] = utf8_encode("Existe usuário cadastrado com este e-mail"); 
        }
			
		return json_encode($response);

	}

    public function listMatchItemsOfUser($user_id){
        $items = array();
        $result = $this->conn->query("SELECT 
                    codigoItem, 
                    nomeItem, 
                    dataCadastro, 
                    descricaoItem, 
                    latitudeItem, 
                    longitudeItem, 
                    raioItem, 
                    nomeCategoria, 
                    nomeStatus, 
                    codigoCliente 
                    from Item natural join Categoria natural join findit.Status join matchitem 
                    on ( item.codigoItem = matchitem.codigoItemEncontrado 
                        or item.codigoItem = matchitem.codigoItemPerdido)  
                    where codigoCliente = '$user_id' 
                        and if(nomeStatus = 'Perdido', 
                            if(isnull(dataVisualizacaoClienteItemPerdido), true, false),
                             if(isnull(dataVisualizacaoClienteItemEncontrado), true, false)) 
                    ORDER BY dataCadastro desc");

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $items[] = array("codigoItem" => $row['codigoItem'], "nomeItem" => $row['nomeItem'], "dataCadastro" => $row['dataCadastro'], "descricaoItem" => $row['descricaoItem'], "latitudeItem" => $row['latitudeItem'], "longitudeItem" => $row['longitudeItem'], "raioItem" => $row['raioItem'], "nomeCategoria" => $row['nomeCategoria'], "nomeStatus" => $row['nomeStatus'], "fotosItem" => $this->listItemPhotos($row['codigoItem'])); 
            }
        } 
        return json_encode($items);
    }

    public function addMatchItem($lost_item_id, $found_item_id){
        if($this->conn->query("INSERT INTO matchitem (codigoItemPerdido, codigoItemEncontrado) values ('$lost_item_id', '$found_item_id')") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function hasMatchNotNotificate($user_id){
        $result = $this->conn->query("SELECT codigoItem 
                from Item natural join Categoria natural join findit.Status join matchitem 
                on ( item.codigoItem = matchitem.codigoItemEncontrado 
                    or item.codigoItem = matchitem.codigoItemPerdido)
                where codigoCliente = '$user_id' 
                    and if(nomeStatus = 'Perdido', if(notificadoClienteItemPerdido = '0', true, false), if(notificadoClienteItemEncontrado = '0', true, false)); ");
        if ($result->num_rows > 0) {
            return true;
        } else {
             return false;
        }
    }

    public function addNotificateMatchItemLost($user_id){
        if ($this->conn->query("UPDATE matchitem 
                JOIN item ON matchitem.codigoItemPerdido = item.codigoItem
                SET matchitem.notificadoClienteItemPerdido = '1'
                WHERE item.codigoCliente = '$user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function addNotificateMatchItemFound($user_id){
        if ($this->conn->query("UPDATE matchitem 
                JOIN item ON matchitem.codigoItemEncontrado = item.codigoItem
                SET matchitem.notificadoClienteItemEncontrado = '1'
                WHERE item.codigoCliente = '$user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function addVisualizationMatchItemLost($user_id, $another_user){
        if ($this->conn->query("UPDATE matchitem mi
                JOIN item i1 ON mi.codigoItemEncontrado = i1.codigoItem
                JOIN item i2 ON mi.codigoItemPerdido = i2.codigoItem
                SET mi.dataVisualizacaoClienteItemPerdido = now()
                WHERE i1.codigoCliente = '$user_id' and i2.codigoCliente = '$another_user'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function addVisualizationMatchItemFound($user_id, $another_user){
        if ($this->conn->query("UPDATE matchitem mi
                JOIN item i1 ON mi.codigoItemEncontrado = i1.codigoItem
                JOIN item i2 ON mi.codigoItemPerdido = i2.codigoItem
                SET mi.dataVisualizacaoClienteItemEncontrado = now()
                WHERE i1.codigoCliente = '$user_id' and i2.codigoCliente = '$another_user'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function getSizeAndCountOfDescriptionsItem(){
        $result = $this->conn->query("SELECT sum(length(descricaoItem)) as tamanho, 
                                count(descricaoItem) as quantidade from Item");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return array( 'tamanho' => $row['tamanho'], 'quantidade' => $row['quantidade']);
        } else
            return -1;
    }   

    public function getInfoDescription($key, $item_lat, $item_long, $item_radius, $category_name, $status_name, $user_id){
        $info = array();
        $result = $this->conn->query("SELECT codigoItem, 
                        latitudeItem, 
                        longitudeItem, 
                        raioItem, 
                        length(descricaoItem) as tamanho, 
                        ROUND(
                            (LENGTH(lcase(descricaoItem)) - 
                            LENGTH( REPLACE ( lcase(descricaoItem), '$key', ''))
                            ) / 
                            LENGTH('$key')) AS frequencia
                        from item natural join categoria natural join status 
                        WHERE descricaoItem like '%$key%' collate utf8_general_ci 
                            and nomeCategoria = '$category_name' 
                            and nomeStatus <> 'Devolvido' 
                            and nomeStatus <> '$status_name' 
                            and codigoCliente <> '$user_id' 
                        ORDER BY nomeItem");

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {                
                if((doubleval($item_radius) + doubleval($row['raioItem'])) >= $this->distance(doubleval($item_lat), doubleval($item_long), doubleval($row['latitudeItem']), doubleval($row['longitudeItem'])))
                    $info[] = array("codigoItem" => $row['codigoItem'], "tamanho" => $row['tamanho'], "frequencia" => $row['frequencia']);
            }
        }

        return $info;

    }

    function distance($lat1, $lon1, $lat2, $lon2) {

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515 * 1000.609344;

        return $miles;
      
    }


    public function insertItem($item_name, $item_desc, $item_lat, $item_long, $item_radius, $cat_name, $status_name, $user_id) {
        if($this->conn->query("INSERT INTO item 
                        (nomeItem, 
                        descricaoItem, 
                        latitudeItem, 
                        longitudeItem, 
                        raioItem, 
                        codigoCategoria, 
                        codigoStatus, 
                        codigoCliente) 
                        SELECT '$item_name' as nomeItem, 
                        '$item_desc' as descricaoItem, 
                        '$item_lat' as latitudeItem, 
                        '$item_long' as longitudeItem, 
                        '$item_radius' as raioItem, 
                        (select codigoCategoria from Categoria 
                            where nomeCategoria = '$cat_name') as codigoCategoria, 
                        (select codigoStatus from Status 
                            where nomeStatus = '$status_name') as codigoStatus, 
                        '$user_id' as codigoCliente") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function getItemId($item_name, $item_desc, $cat_name, $status_name, $user_id) {
        $result = $this->conn->query("SELECT codigoItem 
                        from item 
                        where nomeItem = '$item_name' 
                        and descricaoItem = '$item_desc' 
                        and codigoCategoria = (select codigoCategoria from Categoria 
                            where nomeCategoria = '$cat_name') 
                        and codigoStatus = (select codigoStatus from Status 
                            where nomeStatus = '$status_name') 
                        and codigoCliente = '$user_id' 
                        order by codigoItem desc");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['codigoItem'];
        } else
            return -1;
    }

    public function listConversation($user_id){
        $conversations = array();
        $result = $this->conn->query("SELECT 
                        codigoClienteDestinatario, 
                        nomeCliente, 
                        linkFotoCliente, 
                        ultimaMensagem, 
                        TIMESTAMP(DATE_ADD(dataEnvio, INTERVAL +5 HOUR)) as dataEnvio, 
                        novasMensagens 
                        FROM Conversa 
                        where codigoClienteRemetente = '$user_id' 
                        order by dataEnvio");

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $conversations[] = array("codigoClienteDestinatario" => $row['codigoClienteDestinatario'], "nomeCliente" => $row['nomeCliente'], "linkFotoCliente" => $row['linkFotoCliente'], "ultimaMensagem" => $row['ultimaMensagem'], "dataEnvio" => $row['dataEnvio'], "novasMensagens" => $row['novasMensagens']); 
            }
        } 
        return json_encode($conversations);
    }

    public function listItem($user_id, $complementQuery){
        $items = array();
        $result = $this->conn->query("SELECT codigoItem, 
                        nomeItem, 
                        TIMESTAMP(DATE_ADD(dataCadastro, INTERVAL +5 HOUR)) as dataCadastro, 
                        descricaoItem, 
                        latitudeItem, 
                        longitudeItem, 
                        raioItem, 
                        nomeCategoria, 
                        nomeStatus 
                        from item natural join categoria natural join status 
                        where codigoCliente = '$user_id' 
                            and " . $complementQuery . " 
                        order by dataCadastro DESC");

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $items[] = array("codigoItem" => $row['codigoItem'], "nomeItem" => $row['nomeItem'], "dataCadastro" => $row['dataCadastro'], "descricaoItem" => $row['descricaoItem'], "latitudeItem" => $row['latitudeItem'], "longitudeItem" => $row['longitudeItem'], "raioItem" => $row['raioItem'], "nomeCategoria" => $row['nomeCategoria'], "nomeStatus" => $row['nomeStatus'], "fotosItem" => $this->listItemPhotos($row['codigoItem'])); 
            }
        } 
        return $items;
    }

    public function listItemPhotos($item_id){
        $photos = array();
        $result = $this->conn->query("SELECT linkFotoItem 
                        from fotoitem 
                        where codigoItem = '$item_id'");
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $photos[] = $row['linkFotoItem'];
            }
        } 
        return $photos;
    }

    public function insertPictureItem($item_id, $url_picture){
        if($this->conn->query("INSERT into fotoitem (linkFotoItem, 
                        codigoItem) 
                        values ('$url_picture', 
                        '$item_id')") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function postMessage($user_id, $another_user_id, $message){        
        if($this->conn->query("INSERT INTO chatmensagem (
                        codigoClienteRemetente, 
                        codigoClienteDestinatario, 
                        conteudoMensagem) 
                        VALUES ('$user_id', 
                        '$another_user_id',
                        '$message')") === TRUE) {
            return true;
        } else {
            $response = array();
            $response['error'] = true;
            $response['message'] = $this->conn->error; 
            echo json_encode($response);
        }  
    }

    public function getNewsMessages($user_id){
        $messages = array();
        $result = $this->conn->query("SELECT 
                        c.codigoClienteDestinatario, 
                        c.nomeCliente, 
                        c.linkFotoCliente, 
                        c.ultimaMensagem, 
                        TIMESTAMP(DATE_ADD(c.dataEnvio, INTERVAL +5 HOUR)) as dataEnvio, 
                        c.novasMensagens 
                        from conversa c 
                        join chatmensagem cm on c.codigoClienteRemetente = cm.codigoClienteDestinatario 
                            and c.codigoClienteDestinatario = cm.codigoClienteRemetente 
                        where cm.notificadoMensagem = '0' 
                            and c.codigoClienteRemetente = '$user_id'
                        group by c.codigoClienteRemetente order by dataEnvio desc");
        if ($result->num_rows > 0) {            
            while($row = $result->fetch_assoc()) {
                $messages[] = array(
                        "codigoClienteDestinatario" => $row['codigoClienteDestinatario'],
                        "nomeCliente" => $row['nomeCliente'],
                        "linkFotoCliente" => $row['linkFotoCliente'], 
                        "ultimaMensagem" => $row['ultimaMensagem'], 
                        "dataEnvio" => $row['dataEnvio'], 
                        "novasMensagens" => $row['novasMensagens'], 
                        "mensagens" => $this->getContentNewMessages(
                            $row['codigoClienteDestinatario']));
                $this->addNotificateChatMessage(
                        $row['codigoClienteDestinatario'], 
                        $user_id);

            }
        } 
        return $messages;
    }

    public function addNotificateChatMessage($user_id, $another_user_id){        
        if ($this->conn->query("UPDATE chatmensagem 
                    set notificadoMensagem = '1' 
                    where codigoClienteRemetente = '$user_id' 
                        and codigoClienteDestinatario = '$another_user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function hasNotReadNotification($user_id){
        $result = $this->conn->query("SELECT 
                        if(sum(novasMensagens) > 0, true, false) hasNotReadNotification
                        from conversa 
                        where codigoClienteRemetente = '$user_id'");
        if($row = $result->fetch_assoc()) {
            return ($row['hasNotReadNotification'] == 1 ? TRUE : FALSE);
        } else {
            return false;
        }
    }

    public function addVisualizationMessages($user_id, $another_user_id){        
        if ($this->conn->query("UPDATE chatmensagem 
                    set dataVisualizacao = now() 
                    where codigoClienteRemetente = '$user_id' 
                        and codigoClienteDestinatario = '$another_user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function getContentNewMessages($user_id){
        $messages = array();
        $result = $this->conn->query("SELECT 
                        conteudoMensagem 
                        from chatmensagem 
                        where codigoClienteRemetente = '$user_id' 
                            and isnull(dataVisualizacao)");
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $messages[] = $row['conteudoMensagem'];
            }
        } 
        return $messages;
    }

    public function getMessages($user_id, $another_user_id, $count_messages){        
        $messages = array();
        if(intval($count_messages) == 0){
            $result = $this->conn->query("SELECT 
                        codigoClienteRemetente,
                        DATE_FORMAT(
                            TIMESTAMP(
                                DATE_ADD(dataEnvio, INTERVAL +5 HOUR)),
                                    '%d/%m/%Y %H:%i:%s') as dataEnvio2,
                        dataEnvio,
                        conteudoMensagem 
                        from chatmensagem 
                        where (codigoClienteRemetente = '$user_id' 
                            and codigoClienteDestinatario = '$another_user_id') 
                            or (codigoClienteRemetente = '$another_user_id' 
                            and codigoClienteDestinatario = '$user_id') 
                        order by dataEnvio");
            $this->addVisualizationMatchItemFound($user_id,$another_user_id);
            $this->addVisualizationMatchItemLost($user_id,$another_user_id);
        } else
            $result = $this->conn->query("SELECT 
                        codigoClienteRemetente,
                        DATE_FORMAT(
                            TIMESTAMP(
                                DATE_ADD(dataEnvio, INTERVAL +5 HOUR)),
                                    '%d/%m/%Y %H:%i:%s') as dataEnvio2,
                        dataEnvio,
                        conteudoMensagem 
                        from chatmensagem 
                        where (codigoClienteRemetente = '$user_id' 
                            and codigoClienteDestinatario = '$another_user_id') 
                            or (codigoClienteRemetente = '$another_user_id' 
                            and codigoClienteDestinatario = '$user_id') 
                        order by dataEnvio 
                        limit $count_messages, 18446744073709551615");
        if ($result->num_rows > 0) {            
            while($row = $result->fetch_assoc()) {
                $messages[] = array(
                    "codigoClienteRemetente" => $row["codigoClienteRemetente"],
                    "dataEnvio" => $row['dataEnvio2'],
                    "conteudoMensagem" => $row['conteudoMensagem']);
            }
        } 
        return $messages;    
    }

    public function getLastActivityOfUser($user_id){        
        $result = $this->conn->query("SELECT 
                        ultimaAtividade between DATE_SUB(
                            now(), 
                            INTERVAL 30 SECOND) 
                            and now() as isOnline,
                        TIMESTAMP(DATE_ADD(
                            ultimaAtividade, INTERVAL +5 HOUR)) as ultimaAtividade
                        FROM Cliente 
                        WHERE codigoCliente = '$user_id'");        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (($row['isOnline'] == 1) ? 'Online' : $row['ultimaAtividade']);
        } else {
            return false;
        }
    }   

    public function updateLastActivityOfUser($user_id){
        
        if ($this->conn->query("UPDATE cliente SET 
                        ultimaAtividade= now() 
                        WHERE codigoCliente= '$user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function updateUser($user_id, $user_name, $user_email, $user_password, $user_contact){
        if ($this->conn->query("UPDATE cliente SET 
                        nomeCliente = '$user_name', 
                        emailCliente = '$user_email', 
                        senhaCliente = '$user_password',  
                        contatoCliente = '$user_contact' 
                        WHERE codigoCliente='$user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function getProfilePictureLink($user_id){
        $result = $this->conn->query("SELECT linkFotoCliente 
                        from Cliente 
                        where codigoCliente = '$user_id'");        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['linkFotoCliente'];
        } else {
            return;
        }
    }

    public function updateUserAndProfilePicture($user_id, $user_name, $user_email, $user_password, $user_contact, $url_picture){
        if ($this->conn->query("UPDATE cliente SET 
                        nomeCliente = '$user_name', 
                        emailCliente = '$user_email', 
                        senhaCliente = '$user_password',  
                        contatoCliente = '$user_contact', 
                        linkFotoCliente = '$url_picture' 
                        WHERE codigoCliente='$user_id'") === TRUE) {
            return true;
        } else {
            return false;
        }
    }

    public function getUserById($id_user){
        $result = $this->conn->query("SELECT * 
                        from Cliente 
                        where codigoCliente = '$id_user'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return array('codigoCliente' => $row['codigoCliente'], 'nomeCliente' => $row['nomeCliente'], 'emailCliente' => $row['emailCliente'], 'senhaCliente' => $row['senhaCliente'], 'contatoCliente' => $row['contatoCliente'], 'linkFotoCliente' => $row['linkFotoCliente']);
        } else {
             return false;
        }

        return json_encode($response);
    }


    public function login($email, $password){
        $result = $this->conn->query("SELECT * 
                        from Cliente 
                        where lcase(emailCliente) = '$email' 
                        and senhaCliente = '$password'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['error'] = false;
            $response['client'] = array('codigoCliente' => $row['codigoCliente'], 'nomeCliente' => $row['nomeCliente'], 'emailCliente' => $row['emailCliente'], 'senhaCliente' => $row['senhaCliente'], 'contatoCliente' => $row['contatoCliente'], 'linkFotoCliente' => $row['linkFotoCliente']);
        } else {
             $response['error'] = true;
             $response['message'] = 'Senha incorreta';
        }

        return json_encode($response);
    }

	
}
 
?>