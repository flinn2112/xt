<?php


/*
  Nur verwendbar für Sources, die einzeln pro Datei sind.
  Hat man mehrere lokale Klassen(z.B. in Reports) wird das nicht 
  funktionieren.
  TODO 220324 - eigentlich könnte man gleich aussteigen, wenn die Datei nicht am Anfang
                die Schlüsselwörter CLASS oder INTERFACE hat.
  Eine Klasse kann Interfaces beinhalten:
           interfaces /XTN/IF_OEHS_TB_API_DATE_TIME .
   Die Methoden dieses Interfaces müssen dann eingesammelt werden und gehen als Methoden
   in die Klasse ein.
   Das bedingt aber, dass das Interface vorher eingesammelt wurde - also Interfaces first.
*/
function processCode($strCode, &$ht_classes, &$ht_interfaces){ 
    $strClassDefs = "" ;
    $strCurrSection = "" ;  //public private usw.
    $strMethods = "" ;
    $ht_methods = array() ;    
    $matches = array() ;
    //Zeilen splitten
    $strToken = strtok($strCode, "\n");

    print("=================================PROCESSCODE==========%s===========================================\n") ;
   
    

    while ($strToken !== false) {
        if( preg_match("/public section/i", $strToken, $matches)) {
            printf("Found public in: %s ", $strToken) ;
             $strCurrSection = "+" ;
        }
        if( preg_match("/private section/i", $strToken, $matches)) {
            printf("Found private in: %s ", $strToken) ;
            $strCurrSection = "-" ;
        } 
        if( preg_match("/protected section/i", $strToken, $matches)) {
            printf("Found protected in: %s ", $strToken) ;
            $strCurrSection = "#" ;
        }
        switch ($strCurrSection) {
            case "+":
                echo "processing public\n";
                break;
            case "-":
                echo "processing private\n";
                break;
            case "#":
                echo "processing protected\n";
                break;
            default: 
                $strCurrSection = "" ;
        }

        if( preg_match("/\s*methods\s*/i", $strToken) ){
            $strToken = preg_replace("/\s*methods\s*/i", "", $strToken) ;
            $strToken = preg_replace("/\s*|\./", "", $strToken) ;  //" ." am ende weg
            printf( "PROCESSCODE Method found: %s%s\n", $strCurrSection, $strToken);
            $ht_methods[$strToken] = $strCurrSection. $strToken ;            
        }
      
        if( preg_match("/\s*interfaces.*\.\s*/i", $strToken) ){
            $strToken = trim($strToken) ;            
            $strToken = preg_replace("/\s*interfaces\s*/i", "", $strToken) ;
            $strToken = preg_replace("/\s*|\./", "", $strToken) ;  //" ." am ende weg
            printf( "PROCESSCODE Interface found: %s%s\n", $strCurrSection, $strToken);
            $ht_methods[$strToken] = $strCurrSection. $strToken ;            
        }

      
      $strToken = strtok("\n");
    }

    $strCode = preg_replace("/\n|\r\n/", " ", $strCode); //flach 
   //echo $strCode. "\r\n\r\n";


    /*  SONDERFALL:
         CLASS ltc_database_0006 DEFINITION FOR TESTING DURATION
         wird gefunden(korrekt).
         Aber - da kann dann richtig noch was dranhängen - 

    */
    //Klasse im String:
    if ( preg_match("/\s*class\s+[A-Za-z\/_0-9]*\s*definition/i", $strCode, $matches) ) {
        //den Klassennamen isolieren
           //printf("---MATCH:\n %s\n", $matches[0]) ;
           $matches[0] = preg_replace("/.*class\s*/", "", $matches[0]);
           $matches[0] = preg_replace("/\s*definition\s*.*/i", "", $matches[0]);
        //
        $strClassname = $matches[0] ;        
        //$strClassname = preg_replace("/^\s*CLASS\s*.*/i", "", $strClassname) ;

        //  inheriting from /XTN/CL_OEHS_TB_MCI_MESSAGE_05 create public.
        //  match: inheriting from /XTN/CL_OEHS_TB_MCI_MESSAGE_05
        if(preg_match("/inheriting from\s*[\/_A-Za-z0-9]*/i", $strCode, $matches)) { //vererbt
            $strSuperClass = preg_replace("/\s*inheriting from\s*/i", "", $matches[0]) ;
            $strClassname = preg_replace("/\s*class\s*/i", "", $strClassname) ;
            $strClassname = sprintf("\"%s\" extends \"%s\"", $strClassname, $strSuperClass) ;
            
            printf("----------->>>>>>> FOUND inherit Class [%s] \n", $strClassname) ;
        }else{
            print("----------->>>>>>> does not inherit\n") ;
            $strClassname = preg_replace("/\s*class\s*/i", "", $strClassname) ;
            $strClassname = sprintf("\"%s\" ", $strClassname) ;
        }

        if(preg_match("/^CL_ISHMED|^UNIT_TEST|^LCL_|^LTC_|^CL_ISH/i", $strClassname, $matches)) { 
            //die nicht
            printf("Filtered out %s\n", $strClassname );
        }
        else{

            if(  isset ($ht_classes[$strClassname]) ){ //schon drin?
                //printf("%s already inserted\n",  $ht_classes[$strClassname]) ;
            
            }else{
            foreach( $ht_methods as $m){
                $strMethods = $strMethods. ";". $m ;
            }
            $ht_classes[$strClassname] = sprintf( "%s;%s",  $strClassname, $strMethods);  //insert
            printf("inserting class [%s] \n", sprintf( "%s;%s",  $strClassname, $strMethods)) ;
            }
        }
        
        print("======================================================================================\n") ; //processcode

        //print("\n") ;
    } //class

    //Interface im String:
    if ( preg_match("/^\s*interface\s+[A-Za-z\/_0-9]*/i", $strCode, $matches) ) {
        foreach( $ht_methods as $m){
            $strMethods = $strMethods. ";". $m ;
        }
        $strClassname = preg_replace("/\s*interface\s*/", "", $matches[0]) ;
        printf("inserting  I N T E R F A C E  [%s] \n", sprintf( "%s;%s",  $strClassname, $strMethods)) ;
        $ht_interfaces[$strClassname] = sprintf( "%s;%s",  $strClassname, $strMethods);  //insert
     }
    


}


//plantUML
//Weiterentwicklung: 
//Methoden kamen dazu, deshalb muss der Eingangsstring aufgetrennt werden.
/*
    Eingang ist eine Liste in einem String, Klassenname und folgend Methoden durch ";" getrennt.
    
*/
function createClass($strContents, $ht_interfaces, $strPrefix,  //für interfaces
                           $bMethodsOnly, $bDebug){

    $strMethods = "" ;
    $strContents = preg_replace("/;;/", ";", $strContents) ; //leere weg
    if($bDebug) printf("createClass - received: %s\n", $strContents) ;
    $rParts = explode(";", $strContents) ;
    if( 0 == count($rParts)  ){ //keine Methoden drin
        if($bDebug)  printf("Parts empty: [%s]\n", $strContents ) ;
    }else{
        
        //fängt mit dem 2.ten Element an, erstes ist die Klasse.
        for($i = 1; $i < count($rParts);$i++){
            
            if( empty($rParts[$i])) continue ;
            
            //möglicherweise referiert der Eintrag ein Interface
            $strName = preg_replace("/^[\+\-\#]/", "", $rParts[$i]); //die Modifier könnten drin stehen - muss weg
            if( isset($ht_interfaces[$strName])){                
                if($bDebug) printf("createClass: Found Interface [%s] Value: %s\n", $strName, $ht_interfaces[$strName] ) ;
                $strMethods = $strMethods. createClass($ht_interfaces[$strName], $ht_interfaces, $rParts[$i]. "~", TRUE, $bDebug) ;
                if($bDebug) printf("createClass: Appending Interface Methods [%s] Pref: %s\n", $strMethods, $rParts[$i] ) ;
            }else{
                if( empty($strPrefix))
                    $strMethods =  $strMethods. $rParts[$i]. "\n" ;
                else
                    $strMethods =  $strMethods. $strPrefix. $rParts[$i]. "\n" ;
                    if($bDebug) printf("createClass: Part [%s]\n", $rParts[$i] ) ;
            }
            
        }        
    }
    if( TRUE == $bMethodsOnly ){
        //ohne umgebende class
        return sprintf("%s\n",  $strMethods ) ;
    }else
        return sprintf("class %s {\n%s } \n", $rParts[0], $strMethods ) ;
//print_r($rParts);
}

//200324 - über File *.CI anfangen
/*
Aufsammeln von Klassen und Interfaces.
zu CP/IP Dateien werden die Includes gesammelt und prozessiert.
Die Methoden der Interfaces müssen dann eingesammelt werden und gehen als Methoden
   in die Klasse ein.
   Das bedingt aber, dass das Interface vorher eingesammelt wurde - also Interfaces first.

*/
function collectStuff($strPath, $strPackageName){
    $matches = NULL ;
    $strContentAll = "" ;
    $strContentInterfaces = "" ;  //interfaces getrennt, dann als erstes in den Gesamtstring
    $bIsInterface = FALSE ;
    $d = dir($strPath);
    $ht_classes     = array() ;
    $ht_interfaces  = array() ;
    $iCount = 0 ;
    while (($file = $d->read()) !== false){
        $iCount++ ;        
        //muss natürlich leer sein vor nächstem Durchlauf.
        $strContentAll = "" ;    
        $strContentInterfaces = "" ;        
        if( is_dir($file) ){
            
        }
        else{//in ...ci.aba stehen die Includes drin
            if( preg_match("/.*cp\.aba$/i", $file)){ //interface
                printf("Class: %s\n", $file) ;
                $bIsInterface = FALSE ;
               //continue ; //nur test raus
            }
            elseif( preg_match("/.*ip\.aba$/i", $file)){ //class
                printf("Interface: %s\n", $file) ;
                $bIsInterface = TRUE ;
            }
            else{ //
                printf("File not matching *cp.aba or *ip.aba: %s\n", $file) ;
                continue ; //überspringen
            }
        
            //alle Includes in einen String aufsammeln.
            printf("Browsing file %s\\%s\n", $strPath, $file) ;
            $strInc = $strPath. "\\". $file ;
            preg_match_all("/\s*include\s+.*/", file_get_contents($strInc), $matches);
            if( NULL != $matches[0])
              printf("%d\n", count($matches[0]));  
            
            //print_r($matches) ;
            for( $i=0; $i < count($matches[0]); $i++) {                  
                $fName = preg_replace("/\s*include\s*/", "", $matches[0][$i] ) ;
                $fName = preg_replace("/\s*\.\s*/", "", $fName ) ;
                $fName = preg_replace("/\/XTN\//i", "", $fName ) ;
                printf("INCLUDE FOUND: %s\n", $fName);  
                if( preg_match("/ccau\s*$/i", $fName)){
                    printf("%s filtered out\n", $fName) ;
                    continue ;
                }
                //print($matches[0]. "\n") ;
                //Alle includes werden in einen String eingesammel
                $fName = sprintf("%s\\%s.aba", $strPath, $fName) ;
                if( !file_exists($fName)){
                    printf("collectStuff File [%s] not found - skipped \n", $fName) ;
                    
                    continue ; //for
                } 


                if( TRUE == $bIsInterface){
                    $strContentInterfaces = $strContentInterfaces. file_get_contents($fName) ;
                }else{
                    $strContentAll = $strContentAll. file_get_contents($fName) ;
                }
            } //FOR
            $strContentAll = $strContentInterfaces. "\n". $strContentAll;
            printf("%s\n",  $strContentAll) ;
            processCode($strContentAll, $ht_classes, $ht_interfaces) ; 
             
                
//break ;   
           
        //if( $iCount > 15 ) break;
        print("======================================================================================\n") ; 
        print_r($ht_interfaces) ;
        print("======================================================================================\n") ; 

       }
    } //while

    

    //print($strContentAll) ;

    //sort($ht_classes) ;
    printf("=========================================%s=============================================\n", $strPackageName) ;
    printf("package \"%s\" {\n", $strPackageName) ; 
    foreach ($ht_classes as $x){
            //printf("[%d] ---  %s\n", $iCount++, $x) ;
            $strClasses = createClass($x, $ht_interfaces, NULL, FALSE,
             FALSE //DEBUG
             ) ;
            print($strClasses) ;
        }
        print("\n}\n") ; 
    print("======================================================================================\n") ;    
}

function enum($strPath) {
    $iCount = 0 ;
    $d = dir($strPath);
    $ht_classes = array();
echo "Path: " . $d->path . "\n";

while (($file = $d->read()) !== false){
    $iCount++ ;
    
    if( is_dir($file) ){
        
    }
    else{
        printf("\nFILE: %s\n", $file) ;
        processCode(file_get_contents($strPath. "\\". $file), $ht_classes) ;     
    }
    //if( $iCount > 15 ) break;
    
} //while
    $iCount = 0 ;
    sort($ht_classes) ;
    foreach ($ht_classes as $x){
            //printf("[%d] ---  %s\n", $iCount++, $x) ;
            createClass($x) ;
        }
        
    $d->close();
    printf("[%d] classes collected.\n", $iCount) ;
}

//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_MCI_XTN", "OEHS_TB_MCI") ;
//collectStuff("C:\\Users\\kempf\Documents\\ABAP\XTNOEHS_TB_BL_XTN", "OEHS_TB_BL_XTN") ;

//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_ADK_XTN", "OEHS_TB_ADK") ;

//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_API_XTN", "OEHS_TB_API") ;
//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_BADI_XTN", "OEHS_TB_BADI") ;

//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_NM_BL_XTN", "OEHS_NM_BL_XTN") ;
//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_NM_API_XTN", "OEHS_NM_API_XTN") ;
//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_NM_MCI_XTN", "OEHS_NM_MCI_XTN") ;
//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_REST_XTN", "OEHS_TB_REST_XTN") ;
//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_WP_XTN",  "OEHS_TB_WP_XTN") ;

//collectStuff("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_WF_XTN",  "OEHS_TB_WF_XTN") ;


//collectStuff("C:\\temp\\RCC\\XTNRCC_PMD_XTN", "RCC_PMD") ;

//RCC
//collectStuff("C:\\temp\\RCC\\XTNRCC_BASE_XTN", "RCC_BASE") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_APP_IN_XTN", "RCCAPP_IN") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_APP_OUT_XTN", "RCCAPP_OUT") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_BAR_XTN", "RCCAPP_BAR") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_CTX_XTN", "RCC_CTXT") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_PMD_XTN", "RCC_PMD") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_ORD_XTN", "RCC_ORD") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_SYS_XTN", "RCC_SYS") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_OKR_XTN", "RCC_OKR") ;

//collectStuff("C:\\temp\\RCC\\XTNRCC_MSD_XTN", "RCC_MSD") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_MSA_XTN", "RCC_MSA") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_MCP_XTN", "RCC_MCP") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_MCI_XTN", "RCC_MCI") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_MCI_EHF_XTN", "RCC_EHF") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_MAP_XTN", "RCC_MAP") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_IAP_XTN", "RCC_IAP") ;
//collectStuff("C:\\temp\\RCC\\XTNRCC_HPS_251_XTN", "RCC_IAP") ; //nicht aussagekräftig - auslassen
//collectStuff("C:\\temp\\RCC\\XTNRCC_HCM_XTN", "RCC_HCM") ; 
//collectStuff("C:\\temp\\RCC\\XTNRCC_HCM_BAR_XTN", "RCC_HCM_BAR") ; 


//collectStuff("C:\\temp\\RCC\\XTNRCC_DRX_TIMES_XTN", "RCC_DRX_TIMES") ; 
//collectStuff("C:\\temp\\RCC\\XTNRCC_DRX_TEAM_XTN", "RCC_DRX_TEAM") ; 


//collectStuff("C:\\temp\\RCC\\XTNRCC_DRX_SD_XTN", "RCC_DRX_SD") 
//collectStuff("C:\\temp\\RCC\\XTNRCC_DRX_DOC_XTN", "RCC_DRX_DOC") ;  
//collectStuff("C:\\temp\\RCC\\XTNRCC_DOC_XTN", "RCC_DOC") ; 
//collectStuff("C:\\temp\\RCC\\XTNRCC_DFT_XTN", "RCC_DFT") ;   


//ollectStuff("C:\\temp\\FHIR\\XTNFHIR_RESOURCE_XTN", "FHIR_RESOURCES") ; 
//collectStuff("C:\\temp\\FHIR\\XTNFHIR_REST_XTN", "FHIR_REST") ;   
    
//collectStuff("C:\\temp\\OCI\\XTN", "OCI") ;
collectStuff("C:\\temp\\OCI\\XTNOCI_BASE_XTN", "OCI_BASE") ;
collectStuff("C:\\temp\\OCI\\XTNOCI_COM_XTN", "OCI_COM") ;


/*


//enum("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_UNIT_TEST_XTN") ;

enum("C:\Users\kempf\Documents\ABAP\XTNOEHS_TB_WF_XTN") ;

enum("C:\Users\kempf\Documents\ABAP\XTNOEHS_NM_API_XTN") ;
enum("C:\Users\kempf\Documents\ABAP\XTNOEHS_NM_BL_XTN") ;


enum("C:\\temp\\timerbeestuff") ;

*/

?>
