<?php if($tblAuditFindings0V0s){
    	foreach ($tblAuditFindings0V0s as $tblAuditFindings0V0):    
$def = $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'];
			$result["TblAuditFindings0V0"]["FindingNumber"] = $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'];
			$result["TblAuditFindings0V0"]["Auditor"] = $tblAuditFindings0V0['Auditor']['name'];
			$result["TblAuditFindings0V0"]["Auditee"] = $tblAuditFindings0V0['Auditee']['name'];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'xml',$tblAuditFindings0V0['TblAuditFindings0V0']['id'],$linkedTable['CustomTable']['id'])); 
                $result[Inflector::classify($linkedTable['CustomTable']['table_name'])] = $res;
        }

			$result["TblAuditFindings0V0"]["PreparedBy"] = $tblAuditFindings0V0["PreparedBy"]["name"];
			$result["TblAuditFindings0V0"]["ApprovedBy"] = $tblAuditFindings0V0["ApprovedBy"]["name"];if($tblAuditFindings0V0['TblAuditFindings0V0']['response_from_auditee'])$dt =   date(Configure::read('dateTimeFormat'),strtotime($tblAuditFindings0V0['TblAuditFindings0V0']['created'])) ;
    else $dt =  "--";
			$result["TblAuditFindings0V0"]["Created"] = $dt;
$results[str_replace(' ','',$def)] = $result;
	endforeach; 
} ?>
<?php
    $returnjson['results']['result'] = $results;
    $returnjson['results']['paging'] = $this->Paginator->request->params['paging'];
    $xml = Xml::fromArray($returnjson, array('pretty' => true));
    $this->response->body($xml->asXML());
    $this->response->statusCode(200);
    $this->response->type('xml');
    $this->response->send();
    exit;   
?>