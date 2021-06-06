<?php
/***************************************************************************
 * Title: MyGreensens
 *
 * Author: PiTo
 * 
 * GITHUB: <https://github.com/SymPiTo/MySymApps/tree/master/MySymApps>
 * 
 * Version: 1.0
 *************************************************************************** */
require_once(__dir__.'/../libs/traits.php');

class MyGreensens extends IPSModule {

    use DebugHelper,
        ProfileHelper;

/* 
___________________________________________________________________________ 
    Section: Internal Module Functions
    Die folgenden Funktionen sind Standard Funktionen zur Modul Erstellung.
___________________________________________________________________________ 
*/
    /* 
    ------------------------------------------------------------ 
        Function: Create  
        Create() Wird ausgeführt, beim anlegen der Instanz.
    -------------------------------------------------------------
    */
    public function Create() {
    //Never delete this line!
        parent::Create();

        //Register Properties from form.json
        $this->RegisterPropertyBoolean("ID_active", false);
        $this->RegisterPropertyString("ID_Login", "");
        $this->RegisterPropertyString("ID_Passwort", "");
        $this->RegisterPropertyInteger("ID_Sensors", 6);
        $this->RegisterPropertyInteger("ID_Interval", 0);

        $this->RegisterProfile(vtFloat, "GS.Temperature", "Temperature", $prefix = '', $suffix = '°C', $minvalue = 0, $maxvalue = 100, $stepsize = 0.5, $digits = 1, $associations = null);
        $this->RegisterProfile(vtFloat, "GS.Humidity", "Fog", $prefix = '', $suffix = '%', $minvalue = 0, $maxvalue = 100, $stepsize = 1, $digits = 0, $associations = null);
        $this->RegisterProfile(vtFloat, "GS.Illumination", "Sun", $prefix = '', $suffix = 'lx', $minvalue = 0, $maxvalue = 10000, $stepsize = 1, $digits = 0, $associations = null);
        $associations = [
            [0, $this->Translate('very dry'), '', 0xFF0000],
            [1, $this->Translate('dry'), '', 0x00FF00],
            [2, $this->Translate('water plant'), '', 0x00FF00],
            [3, $this->Translate('enough water'), '', 0x00FF00],
        ];
        $this->RegisterProfile(vtInteger, "GS.Status", "Sun", $prefix = '', $suffix = '', $minvalue = 0, $maxvalue = 3, $stepsize = 1, $digits = 0, $associations = null);

        //Register Timer
        $this->RegisterTimer("updatePlant", 0, 'GS_Update($_IPS[\'TARGET\']);');

    } //Function: Create End
    /* 
    ------------------------------------------------------------ 
        Function: ApplyChanges  
        ApplyChanges() Wird ausgeführt, beim anlegen der Instanz.
        und beim ändern der Parameter in der Form
    -------------------------------------------------------------
    */
    public function ApplyChanges(){
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();
    
            

    
 


        $totalSensors = $this->ReadPropertyInteger("ID_Sensors");
        for ($zaehler = 6; $zaehler >= $totalSensors; $zaehler--) {
            $this->UnregisterVariable ("sensorID".$zaehler);
            $this->UnregisterVariable ("sensorName".$zaehler); 
            $this->UnregisterVariable ("sensorStatus".$zaehler);
            $this->UnregisterVariable ("ID_Temp".$zaehler);
            $this->UnregisterVariable ("ID_Illumination".$zaehler);
            $this->UnregisterVariable ("ID_Moisture".$zaehler);
            $this->UnregisterVariable ("ID_State".$zaehler);
            $this->UnregisterVariable ("ID_Link".$zaehler);
        }
        //Register Variables
        for ($zaehler = 0; $zaehler <= $totalSensors-1; $zaehler++) {
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("sensorID".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableInteger ("sensorID".$zaehler, "Sensor".$zaehler.$this->Translate(":Sensor ID"), "" , $zaehler*8+1);
                IPS_SetInfo ($variablenID, "");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("sensorName".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableString ("sensorName".$zaehler, "Sensor".$zaehler.$this->Translate(":Plant name"), "", $zaehler*8+2); 
                IPS_SetInfo ($variablenID, "");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("sensorStatus".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableBoolean ("sensorStatus".$zaehler, "Sensor".$zaehler.$this->Translate(":Sensor Status"), "", $zaehler*8+3);
                IPS_SetInfo ($variablenID, "");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("ID_Temp".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableFloat ("ID_Temp".$zaehler, "Sensor".$zaehler.$this->Translate(":Temperature"), "GS.Temperature", $zaehler*8+4);
                IPS_SetInfo ($variablenID, "WSS");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("ID_Illumination".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableFloat ("ID_Illumination".$zaehler, "Sensor".$zaehler.":Helligkeit", "GS.Illumination", $zaehler*8+5);
                IPS_SetInfo ($variablenID, "WSS");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("ID_Moisture".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableFloat ("ID_Moisture".$zaehler, "Sensor".$zaehler.":Feuchte", "GS.Humidity", $zaehler*8+6);
                IPS_SetInfo ($variablenID, "WSS");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("ID_State".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableInteger ("ID_State".$zaehler, "Sensor".$zaehler.":Zustand", "GS.Status", $zaehler*8+7);
                IPS_SetInfo ($variablenID, "WSS");
            }
            if (!IPS_VariableExists(@IPS_GetVariableIDByName("ID_Link".$zaehler, $this->InstanceID))){
                $variablenID = $this->RegisterVariableString ("ID_Link".$zaehler, "Sensor".$zaehler.":Image URL", "", $zaehler*8+8);
                IPS_SetInfo ($variablenID, "WSS");
            }
        }

        if($this->ReadPropertyBoolean("ID_active")){
            $this->SetBuffer("token", "");
            $this->SetBuffer("timestamp", "");
            $this->SetBuffer("valid", false);

            $this->FetchToken();
            $updateTime = $this->ReadPropertyInteger("ID_Interval");
            $this->SetTimerInterval("updatePlant", $updateTime);
        }
        else {
            //Timer off
            $this->SetTimerInterval("updatePlant", 0);
        }                   
    } //Function: ApplyChanges  End
    /* 
    ------------------------------------------------------------ 
        Function: Destroy  
            Destroy() wird beim löschen der Instanz 
            und update der Module aufgerufen
    -------------------------------------------------------------
    */
    public function Destroy() {
        //Never delete this line!
        parent::Destroy();
    } //Function: Destroy End
    /* 
    ------------------------------------------------------------ 
        Function: MessageSink  
            MessageSink() wird aufgerufen wenn registrierte 
            Meldung 
    -------------------------------------------------------------
    */    protected function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady(); 
                break;
  
        }
    }
/* 
________________________________________________________________________________________________
    Section: Public Functions
    Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wie folgt zur Verfügung gestellt:
    
    GS_XYFunktion($Instance_id, ... );
________________________________________________________________________________________________________________________ 
*/


    
    //-----------------------------------------------------------------------------
    /* Function: GetPlantData
    ...............................................................................
    Beschreibung: Holt die Sensor Daten per API
    ...............................................................................
    Parameters: 
        none
    ...............................................................................
    Returns:    
        $plantdata =>Sensordaten als Array {}
            ['sensorID']
            ['plantNameDE']
            ['status']
            ['temperature']
            ['illumination']
            ['moisture']
            ['state']
            ['link']
    ------------------------------------------------------------------------------  */
    public function GetPlantData() {
        //prüfen ob token noch gültig ist
        $valid = $this->CheckValidToken();
        if($valid == false) {
            $this->FetchToken();
            $valid = $this->CheckValidToken();
        }
        if($valid == true) {
            $token = $this->GetBuffer("token");
            $url = "https://api.greensens.de/api/Plants";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            // prüfen ob API Schnittstelle ansprechbar
            if(curl_exec($curl) === false){
                //echo 'Curl-Fehler: ' . curl_error($curl);
                if(curl_errno($curl)){
                   // echo 'cURL-Fehler: ' . curl_error($ch);
                   $errorno = curl_errno($curl);
                   $error_message = curl_strerror($errno);
                   $this->SendDebug("cURL error ({$errno}):\n {$error_message}","");
                }
            }
            else {
                $this->SendDebug($this->Translate("Received data frm API without error."),"");
            }
            //Kopfdaten
            $headers = array(
            "Accept: application/json",
            "Authorization: Bearer $token",
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            //for debug only!
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            // Daten holen
            $resp = curl_exec($curl);
            curl_close($curl);
            //update data
            $data = json_decode($resp, true);
             
            $plantdata = $data['data']['registeredHubs'][0]['plants'];
            //Daten in Variablen schreiben
            $totalSensors = count($plantdata);
            for ($zaehler = 0; $zaehler <= $totalSensors-1; $zaehler++) {
                $this->SetValue("sensorID".$zaehler, $plantdata[$zaehler]['sensorID']);
                $this->SetValue("sensorName".$zaehler, $plantdata[$zaehler]['plantNameDE']); 
                $this->SetValue("sensorStatus".$zaehler, $plantdata[$zaehler]['status']); 
                $this->SetValue("ID_Temp".$zaehler, $plantdata[$zaehler]['temperature']); 
                $this->SetValue("ID_Illumination".$zaehler, $plantdata[$zaehler]['illumination']); 
                $this->SetValue("ID_Moisture".$zaehler, $plantdata[$zaehler]['moisture']);  
                $this->SetValue("ID_State".$zaehler, $plantdata[$zaehler]['state']);  
                $this->SetValue("ID_Link".$zaehler, $plantdata[$zaehler]['link']);  
            }
            $this->SendDebug($this->Translate("Sensor data:"), $plantdata,0);
            return $plantdata;
        }
        else {
            $this->SendDebug($this->Translate("Error"), $this->Translate("No sensor data received.", 0);
            return false;
        }
    }  //End



    //-----------------------------------------------------------------------------
    /* Function: Update
    ...............................................................................
    Beschreibung: Funktion wird vom Timergetriggert. Sensordaten werden im
        eingestellten Zeit Interval abgefragt 
    ...............................................................................
    Parameters: 
        none
    ...............................................................................
    Returns:    
        none
    ------------------------------------------------------------------------------  */
    public function Update() {
        $this->GetPlantData();
    }


/* 
_______________________________________________________________________
    Section: Private Funtions
    Die folgenden Funktionen sind nur zur internen Verwendung verfügbar
    Hilfsfunktionen
______________________________________________________________________
*/ 
    //-----------------------------------------------------------------------------
    /* Function: FetchToken
    ...............................................................................
    Beschreibung: holt ein Token von der API. 
    Token ist nur 7 Tage gültig.
    ...............................................................................
    Parameters: 
        none
    ...............................................................................
    Returns:    
        $token
    ------------------------------------------------------------------------------  */
    protected function FetchToken(){
        $url = "https://api.greensens.de";   
        $auth_url ="/api/users/authenticate";
        $path =$url.$auth_url;
        $login = $this->ReadPropertyString("ID_Login");
        $password = $this->ReadPropertyString("ID_Passwort");

        $curl = curl_init($path);
        curl_setopt($curl, CURLOPT_URL, $path);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array(
           "Content-Type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
        //$data = '{"login":$login,"password":$password}';
        $data = array(
            "login" => $login,
            "password" => $password
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $resp = curl_exec($curl);
        curl_close($curl);
        $obj= json_decode($resp);
        
        $token =  $obj->data->token;
        $timestamp = time();
        //Schreibt  in den Buffer "Databuffer"
        $this->SetBuffer("token", $token);
        $this->SetBuffer("timestamp", $timestamp);
        if($token != ""){
            $this->SetBuffer("valid", true);
            $this->SendDebug($this->Translate("received Token:"), $token, 0);
            return $token;
        }
        else{
            $this->SendDebug($this->Translate("Error:"), $this->Translate("No Token received"), 0);
            return false;
        }
    }  //End

    
    //-----------------------------------------------------------------------------
    /* Function: CheckValidToken
    ...............................................................................
    Beschreibung: Prüfen ob Token abgelaufen ist. Gültigkeit 7 Tage
    ...............................................................................
    Parameters: 
        none
    ...............................................................................
    Returns:    
        $valid => Token gültig "true/false"
    ------------------------------------------------------------------------------  */
    protected function CheckValidToken(){
        $timestamp = $this->GetBuffer("timestamp");
        $aktTime = time();
        $time = $aktTime - $timestamp;
        // 7 Tage = 7*24*60*60  =  604800 Sekunden

        if(($aktTime - $timestamp) > 604800){
            $this->SetBuffer("valid", false); 
            $valid = false;
            $this->SendDebug("Warning:", $this->Translate("Token has expired."), 0);
        }
        else{
            $this->SetBuffer("valid", true);
            $valid = true;
            $this->SendDebug("Token:", $this->Translate("is valid."), 0);
        }
        return $valid;
    }  //End


    //-----------------------------------------------------------------------------
    /* Function: KernelReady
    ...............................................................................
    Beschreibung: Wird ausgeführt wenn der Kernel hochgefahren wurde.
    ...............................................................................
    Parameters: 
        none
    ...............................................................................
    Returns:    
        none
    ------------------------------------------------------------------------------  */

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }


} //end Class



