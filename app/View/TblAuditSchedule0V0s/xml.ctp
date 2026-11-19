<?php if($tblAuditSchedule0V0s){
    	foreach ($tblAuditSchedule0V0s as $tblAuditSchedule0V0):    
$def = $tblAuditSchedule0V0['TblAuditSchedule0V0']['audit_number'];
			$result["TblAuditSchedule0V0"]["AuditNumber"] = $tblAuditSchedule0V0['TblAuditSchedule0V0']['audit_number'];
			$result["TblAuditSchedule0V0"]["CurrentStatus"] = $customArray['currentStatuses'][$tblAuditSchedule0V0['TblAuditSchedule0V0']['current_status']];
        foreach($linkedTables as $linkedTable){
                $res = $this->requestAction(array('controller'=>$linkedTable['CustomTable']['table_name'], 'action'=>'xml',$tblAuditSchedule0V0['TblAuditSchedule0V0']['id'],$linkedTable['CustomTable']['id'])); 
                $result[Inflector::classify($linkedTable['CustomTable']['table_name'])] = $res;
        }

			$result["TblAuditSchedule0V0"]["PreparedBy"] = $tblAuditSchedule0V0["PreparedBy"]["name"];
			$result["TblAuditSchedule0V0"]["ApprovedBy"] = $tblAuditSchedule0V0["ApprovedBy"]["name"];if($tblAuditSchedule0V0['TblAuditSchedule0V0']['notes'])$dt =   date(Configure::read('dateTimeFormat'),strtotime($tblAuditSchedule0V0['TblAuditSchedule0V0']['created'])) ;
    else $dt =  "--";
			$result["TblAuditSchedule0V0"]["Created"] = $dt;
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