<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use nusoap_client;
use File;
use Carbon\Carbon;
use DB;
use DomDocument;

class SoapJdeController extends Controller
{

//CREAR CLIENTE EN JDE
    public function CrearClienteJDE($nombre,$identificacion,$company,$correo,$tipo_identificacion){
        $configs = include(base_path().'/config/configuration.php');
        $ambiente = $configs->AMBIENTE;
        if(trim($ambiente) == "PD910" || trim($ambiente) == "JPD910" || trim($ambiente) == "JPD901"){
            $wsdl =  $configs->BSSV_JDE_CLIENTE_PD910;
            $Header_user='bssv';
            $Header_pass='DEMACO';
          }else if(trim($ambiente) == "PY910" || trim($ambiente) == "JPY910"){
            $wsdl =  $configs->BSSV_JDE_CLIENTE_PY910;
            $Header_user='CERTIFSA';
            $Header_pass='zaq1xsw2';
          }else{
            $wsdl =  $configs->BSSV_JDE_CLIENTE_DV910;
            $Header_user='CERTIFSA';
            $Header_pass='zaq1xsw2';
          }

        $client=new nusoap_client($wsdl);
        $client->soap_defencoding = 'UTF-8';
        $client->useHTTPPersistentConnection(); 

        $header='
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:orac="http://oracle.e1.bssv.JP5542SM/">
            <soapenv:Header>
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" soapenv:mustUnderstand="1">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$Header_user.'</wsse:Username>
                        <wsse:Password>'.$Header_pass.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soapenv:Header>';
            $body_recibido='<soapenv:Body>
        <orac:QuickCustomersCreation>
                <ruc>2400252777</ruc>
                <nombre>tyrone</nombre>
                <tipoContribuyente>C</tipoContribuyente>
                <direccion>test</direccion>
                <codigoTelefono>593</codigoTelefono>
                <numeroTelefono>0945329009</numeroTelefono>
                <correo>test@hotmail.com</correo>
                <modo>C</modo>
                <codePais>EC</codePais>
                <descripcionPais>ECUADOR</descripcionPais>
                <codeCiudad>G</codeCiudad>
                <ciudad>GUAYAQUIL</ciudad>
            ';
        $footer='</orac:QuickCustomersCreation>
            </soapenv:Body>
        </soapenv:Envelope>';

        $request_xml =$header.PHP_EOL.$body_recibido.PHP_EOL.$footer;
        $client->send($request_xml);

        File::append(storage_path('logs/CLIENT-SOAP-CALL'.Carbon::now()->format('Y-m').'.log'),PHP_EOL."
        _________________________________________________________________________________________________________________________"
        .PHP_EOL);

        File::append(storage_path('logs/CLIENT-SOAP-CALL'.Carbon::now()->format('Y-m').'.log'),$request_xml.PHP_EOL);

        File::append(storage_path('logs/CLIENT-SOAP-RESPONSE'.Carbon::now()->format('Y-m').'.log'),PHP_EOL."
        _________________________________________________________________________________________________________________________"
        .PHP_EOL);


        File::append(storage_path('logs/CLIENT-SOAP-RESPONSE'.Carbon::now()->format('Y-m').'.log'),$client->responseData);

        if ($client->getError()){
            $error =
                'Servicio [\'Creacion de clientes\']'.PHP_EOL.
                'url [' .  $wsdl.']'.PHP_EOL.
                'request [' . $request_xml.']'.PHP_EOL.
                'date [' .  Carbon::now()->format('Y/m/d H:i:s').']'.PHP_EOL.
                'error [' .  $client->getError().']';

            File::append(storage_path('logs/CLIENT-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),
            PHP_EOL.
            "_________________________________________________________________________________________________________________________"
            .PHP_EOL);

            File::append(storage_path('logs/CLIENT-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] No pudo ser consumido, revisar el CLIENT-SOAP_ERROR.log ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
            File::append(storage_path('logs/CLIENT-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),$error);
            return "500";
        }

       

        if (!$client->getError()){
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($client->responseData);
            $dom->xmlStandalone = true;
            $dom->encoding  = 'UTF-8';
            $dom->formatOutput = true;
    
            //VALIDACION PARA QUE NO SE CREE EL CLIENTE EN BAGISTO SI JDE RESPONDE CON ERROR
            $faultstring = isset($dom->getElementsByTagName('faultstring')[0]->nodeValue) ? $dom->getElementsByTagName('faultstring')[0]->nodeValue : null;
                if ($faultstring){
                    File::append(storage_path('logs/CLIENT-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] Genero el siguiente error ['.$faultstring.'] ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
                    return "500";
                }
            $this->ActualizarCliente($client->responseData);
            return "200";
        }
    }

        
//ACTUALIZAR CLIENTE BAGISTO
    public function ActualizarCliente($xml){
        
        $dom = new DOMDocument();

        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->xmlStandalone = true;
        $dom->encoding  = 'UTF-8';
        $dom->formatOutput = true;

        $an8Cliente = isset($dom->getElementsByTagName('entityId')[0]->nodeValue) ? $dom->getElementsByTagName('entityId')[0]->nodeValue : null;
        $identificacion = isset($dom->getElementsByTagName('entityTaxId')[0]->nodeValue) ? $dom->getElementsByTagName('entityTaxId')[0]->nodeValue : null;

        if($an8Cliente != null){
            DB::table('customers')->where('identification',$identificacion)->update(['an8' =>  $an8Cliente] );
        }
    }

//CREAR ORDEN EN JDE
public function CrearOrdenJDE( $an8, $detail , $orderID ){
    

    $configs = include(base_path().'/config/configuration.php');
    $ambiente = $configs->AMBIENTE;
    if(trim($ambiente) == "PD910" || trim($ambiente) == "JPD910" || trim($ambiente) == "JPD901"){
        $wsdl =  $configs->BSSV_JDE_ORDEN_PD910;
        $Header_user='bssv';
        $Header_pass='DEMACO';
      }else if(trim($ambiente) == "PY910" || trim($ambiente) == "JPY910"){
        $wsdl =  $configs->BSSV_JDE_ORDEN_PY910;
        $Header_user='CERTIFSA';
        $Header_pass='zaq1xsw2';
      }else{
        $wsdl =  $configs->BSSV_JDE_ORDEN_DV910;
        $Header_user='CERTIFSA';
        $Header_pass='zaq1xsw2';
      }

      
     
      if (!$an8){
        File::append(storage_path('logs/ORDER-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] El codigo AN8 esta vacio, no se puede consumir el servicio de JDE ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
        return "500";
    }


    $client=new nusoap_client($wsdl);
    $client->soap_defencoding = 'UTF-8';
    $client->useHTTPPersistentConnection(); 

    $header='
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:orac="http://oracle.e1.bssv.JP420000/">
        <soapenv:Header>
            <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" soapenv:mustUnderstand="1">
                <wsse:UsernameToken>
                    <wsse:Username>'.$Header_user.'</wsse:Username>
                    <wsse:Password>'.$Header_pass.'</wsse:Password>
                </wsse:UsernameToken>
            </wsse:Security>
        </soapenv:Header>';

    $detalle='';  
    
    foreach ($detail as $d) {

        $detalle .= '<detail>
        <processing>
            <actionType>A</actionType>
        </processing>
        <product>
            <item>
                <itemProduct>'.$d['producto'].'</itemProduct>
            </item>
        </product>
        <quantityOrdered>'.$d['cantidad'].'</quantityOrdered>
        <shipTo>
            <entityId>'.$an8.'</entityId>
        </shipTo>
    </detail>'.PHP_EOL;
        }



    $body_recibido='<soapenv:Body>
	<orac:processSalesOrderV2>
		<header>
			<dateOrdered>'.Carbon::now()->format('Y-m-d').'</dateOrdered>
			<dateRequested>'.Carbon::now()->format('Y-m-d').'</dateRequested>
            '.$detalle.'
			<processing>
				<actionType>A</actionType>
				<processingVersion>DEM0011</processingVersion>
			</processing>
			<soldTo>
				<customer>
					<entityId>'.$an8.'</entityId>
				</customer>
			</soldTo>
		</header>';
    $footer='</orac:processSalesOrderV2>
    </soapenv:Body>
    </soapenv:Envelope>';

    $request_xml =$header.PHP_EOL.$body_recibido.PHP_EOL.$footer;

   // return response( $request_xml, 200, [
     //   'Content-Type' => 'application/xml'
    //]);

    $response = $client->send($request_xml);

    File::append(storage_path('logs/ORDER-SOAP-'.Carbon::now()->format('Y-m').'.log'),PHP_EOL."
    _________________________________________________________________________________________________________________________"
    .PHP_EOL);

    File::append(storage_path('logs/ORDER-SOAP-'.Carbon::now()->format('Y-m').'.log'),$request_xml.PHP_EOL);

    File::append(storage_path('logs/ORDER-SOAP-'.Carbon::now()->format('Y-m').'.log'),PHP_EOL."
    _________________________________________________________________________________________________________________________"
    .PHP_EOL);
    File::append(storage_path('logs/ORDER-SOAP-'.Carbon::now()->format('Y-m').'.log'),$client->responseData);

  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->loadXML($client->responseData);
  $dom->xmlStandalone = true;
  $dom->encoding  = 'UTF-8';
  $dom->formatOutput = true;

  //VALIDACION PARA QUE NO SE CREE LA ORDEN EN BAGISTO SI JDE RESPONDE CON ERROR
  $faultstring = isset($dom->getElementsByTagName('faultstring')[0]->nodeValue) ? $dom->getElementsByTagName('faultstring')[0]->nodeValue : null;
    if ($faultstring){
        File::append(storage_path('logs/ORDER-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] Genero el siguiente error ['.$faultstring.'] ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
        return "500";
    }

    //OBTENCION DE EL ID DE JDE DE LA ORDEN PARA GUARDARLO EN EL CAMPO CUSTOM document_number_jde DE BAGISTO
    $documentNumber = isset($dom->getElementsByTagName('documentNumber')[0]->nodeValue) ? $dom->getElementsByTagName('documentNumber')[0]->nodeValue : null;
    if ($documentNumber && $orderID){
        DB::table('orders')->where('id',$orderID)->update(['document_number_jde' =>  $documentNumber] );
    }else{
        return "500";
    }

    //OBTENER ERROR SI SE CAE EL BSSV DE JDE
    if ($client->getError()){
        $error =
            'Servicio [\'Creacion de ordenes\']'.PHP_EOL.
            'url [' .  $wsdl.']'.PHP_EOL.
            'request [' . $request_xml.']'.PHP_EOL.
            'date [' .  Carbon::now()->format('Y/m/d H:i:s').']'.PHP_EOL.
            'error [' .  $client->getError().']';

        File::append(storage_path('logs/ORDER-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),
        PHP_EOL.
        "_________________________________________________________________________________________________________________________"
        .PHP_EOL);

        File::append(storage_path('logs/ORDER-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),$error);
        return "500";

    }else{
        return "200";
        //primer commit 2

       // $this->ActualizarCliente($client->responseData,$correo); xd 

    }
}

//CONSULTAR ESTADO ORDEN EN JDE
public function ConsultarOrdenJDE($orderID,$tag){
    
    $orderID='21000109';
    $configs = include(base_path().'/config/configuration.php');
    $ambiente = $configs->AMBIENTE;
    if(trim($ambiente) == "PD910" || trim($ambiente) == "JPD910" || trim($ambiente) == "JPD901"){
        $wsdl =  $configs->BSSV_JDE_ORDEN_PD910;
        $Header_user='bssv';
        $Header_pass='DEMACO';
      }else if(trim($ambiente) == "PY910" || trim($ambiente) == "JPY910"){
        $wsdl =  $configs->BSSV_JDE_ORDEN_PY910;
        $Header_user='CERTIFSA';
        $Header_pass='zaq1xsw2';
      }else{
        $wsdl =  $configs->BSSV_JDE_ORDEN_DV910;
        $Header_user='CERTIFSA';
        $Header_pass='zaq1xsw2';
      }

      
     
      if (!$orderID){
        File::append(storage_path('logs/ORDER-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] El codigo de la orden esta vacio, no se puede consumir el servicio de JDE ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
        return "ERROR";
    }


    $client=new nusoap_client($wsdl);
    $client->soap_defencoding = 'UTF-8';
    $client->useHTTPPersistentConnection(); 

    $header='
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:orac="http://oracle.e1.bssv.JP420000/">
        <soapenv:Header>
            <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" soapenv:mustUnderstand="1">
                <wsse:UsernameToken>
                    <wsse:Username>'.$Header_user.'</wsse:Username>
                    <wsse:Password>'.$Header_pass.'</wsse:Password>
                </wsse:UsernameToken>
            </wsse:Security>
        </soapenv:Header>';

    $body_recibido='<soapenv:Body>
    <orac:getSalesOrder>
       <header>
          <company>00001</company>
          <salesOrderKey>
             <documentCompany>00001</documentCompany>
             <documentNumber>'.$orderID.'</documentNumber>
             <documentTypeCode>SM</documentTypeCode>
          </salesOrderKey>
       </header>';
    $footer=' </orac:getSalesOrder>
    </soapenv:Body>
 </soapenv:Envelope>';

    $request_xml =$header.PHP_EOL.$body_recibido.PHP_EOL.$footer;
    $response = $client->send($request_xml);




  
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($client->responseData);
    $dom->xmlStandalone = true;
    $dom->encoding  = 'UTF-8';
    $dom->formatOutput = true;

    //VALIDACION PARA QUE NO SE CREE LA ORDEN EN BAGISTO SI JDE RESPONDE CON ERROR
    $faultstring = isset($dom->getElementsByTagName('faultstring')[0]->nodeValue) ? $dom->getElementsByTagName('faultstring')[0]->nodeValue : null;
        if ($faultstring){
            File::append(storage_path('logs/ORDER-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] Genero el siguiente error ['.$faultstring.'] ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
            return "ERROR";
        }

        $statusCodeLast=null;
        $detail=null;

    if($tag =='statusCodeLast'){
        $statusCodeLast = isset($dom->getElementsByTagName('statusCodeLast')[0]->nodeValue) ? $dom->getElementsByTagName('statusCodeLast')[0]->nodeValue : null;
        if (!$statusCodeLast){
            File::append(storage_path('logs/ORDER2-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] No se encontro el statusCodeLast  ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
            return "ERROR";
        }
    }

    //return response( $client->responseData , 200, [ 'Content-Type' => 'application/xml']);
    $object = [];
    if($tag =='detail'){
       
     $detail = $dom->getElementsByTagName('detail')  !=null ? $dom->getElementsByTagName('detail') : null;
 

       $dateRequested = isset($dom->getElementsByTagName('dateRequested')[0]->nodeValue) ? $dom->getElementsByTagName('dateRequested')[0]->nodeValue : null;
       $amountTotalOrderDomestic = isset($dom->getElementsByTagName('amountTotalOrderDomestic')[0]->nodeValue) ? $dom->getElementsByTagName('amountTotalOrderDomestic')[0]->nodeValue : null;
       $documentNumber = isset($dom->getElementsByTagName('salesOrderKey')->item(0)->getElementsByTagName('documentNumber')->item(0)->nodeValue) ? $dom->getElementsByTagName('salesOrderKey')->item(0)->getElementsByTagName('documentNumber')->item(0)->nodeValue : null;
       $documentTypeCode = isset($dom->getElementsByTagName('salesOrderKey')->item(0)->getElementsByTagName('documentTypeCode')->item(0)->nodeValue) ? $dom->getElementsByTagName('salesOrderKey')->item(0)->getElementsByTagName('documentTypeCode')->item(0)->nodeValue : null;
       

        if(isset($detail->item(0)->nodeValue)){
            for ($i = 0; $i < $detail->length; $i++) {
                $itemProduct = isset($detail->item($i)->getElementsByTagName('itemProduct')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('itemProduct')->item(0)->nodeValue : null;
                $description1 = isset($detail->item($i)->getElementsByTagName('description1')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('description1')->item(0)->nodeValue : null;
                $description2 = isset($detail->item($i)->getElementsByTagName('description2')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('description2')->item(0)->nodeValue : null;
                $quantityOrdered = isset($detail->item($i)->getElementsByTagName('quantityOrdered')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('quantityOrdered')->item(0)->nodeValue : null;
                $priceExtendedDomestic = isset($detail->item($i)->getElementsByTagName('priceExtendedDomestic')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('priceExtendedDomestic')->item(0)->nodeValue : null;
                $unitOfMeasureCodePricing = isset($detail->item($i)->getElementsByTagName('unitOfMeasureCodePricing')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('unitOfMeasureCodePricing')->item(0)->nodeValue : null;
                $statusCodeLast = isset($detail->item($i)->getElementsByTagName('statusCodeLast')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('statusCodeLast')->item(0)->nodeValue : null;
                $documentLineNumber = isset($detail->item($i)->getElementsByTagName('documentLineNumber')->item(0)->nodeValue) ? $detail->item($i)->getElementsByTagName('documentLineNumber')->item(0)->nodeValue : null;
                


                array_push($object, [
                    'documentNumber' => $documentNumber ,
                    'documentTypeCode' => $documentTypeCode ,
                    'documentLineNumber' => intval($documentLineNumber) ,
                    'itemProduct' => trim($itemProduct) ,
                    'description1' => trim($description1) ,
                    'description2' =>  trim($description2),
                    'quantityOrdered' =>  trim($quantityOrdered),
                    'priceExtendedDomestic' =>  $priceExtendedDomestic,
                    'unitOfMeasureCodePricing' =>  $unitOfMeasureCodePricing,
                    'statusCodeLast' =>  $statusCodeLast,
                    'dateRequested' =>  Carbon::parse($dateRequested)->format('Y-m-d'),
                    'amountTotalOrderDomestic' =>  $amountTotalOrderDomestic,   
            ]);
             
            }
          
         
        }else{
            File::append(storage_path('logs/ORDER2-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'), '['.$wsdl.'] No se encontro el detail  ['.Carbon::now()->format('Y-m-d H:i:s').']'.PHP_EOL);
            return "ERROR";
        }

    }  


    //OBTENER ERROR SI SE CAE EL BSSV DE JDE
    if ($client->getError()){
        $error =
            'Servicio [\'Creacion de ordenes\']'.PHP_EOL.
            'url [' .  $wsdl.']'.PHP_EOL.
            'request [' . $request_xml.']'.PHP_EOL.
            'date [' .  Carbon::now()->format('Y/m/d H:i:s').']'.PHP_EOL.
            'error [' .  $client->getError().']';

        File::append(storage_path('logs/ORDER-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),
        PHP_EOL.
        "_________________________________________________________________________________________________________________________"
        .PHP_EOL);

        File::append(storage_path('logs/ORDER-SEARCH-SOAP_ERROR-'.Carbon::now()->format('Y-m-d').'.log'),$error);
        return "ERROR";

    }else{

        if($tag =='statusCodeLast'){
            return $statusCodeLast;
        }
        if($tag =='detail'){
            //return response( $detail , 200, [ 'Content-Type' => 'application/xml']);
            return $object;
        }  

       
    }
}


}
