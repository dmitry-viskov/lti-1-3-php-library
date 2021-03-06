<?php
namespace IMSGlobal\LTI;

include_once("lti_lineitem.php");

class LTI_Assignments_Grades_Service {

    private $service_connector;
    private $service_data;

    public function __construct(LTI_Service_Connector $service_connector, $service_data) {
        $this->service_connector = $service_connector;
        $this->service_data = $service_data;
    }

    public function put_grade(LTI_Grade $grade, LTI_Lineitem $lineitem = null) {
        if (!in_array("https://purl.imsglobal.org/spec/lti-ags/scope/score", $this->service_data['scope'])) {
            throw new LTI_Exception('Missing required scope', 1);
        }
        $score_url = '';
        if ($lineitem !== null && empty($lineitem->get_id())) {
            $lineitem = $this->find_or_create_lineitem($lineitem);
            $score_url = $lineitem->get_id();
        } else if ($lineitem === null && !empty($this->service_data['lineitem'])) {
            $score_url = $this->service_data['lineitem'] ;
        } else {
            $lineitem = LTI_Lineitem::new()
            ->set_label('default')
            ->set_score_maximum(100);
            $lineitem = $this->find_or_create_lineitem($lineitem);
            $score_url = $lineitem->get_id();
        }
        $score_url .= '/scores';
        return $this->service_connector->make_service_request(
            $this->service_data['scope'],
            'POST',
            $score_url,
            strval($grade),
            'application/vnd.ims.lis.v1.score+json'
        );
    }

    public function find_or_create_lineitem(LTI_Lineitem $new_line_item) {
        if (!in_array("https://purl.imsglobal.org/spec/lti-ags/scope/lineitem", $this->service_data['scope'])) {
            throw new LTI_Exception('Missing required scope', 1);
        }
        $line_items = $this->service_connector->make_service_request(
            $this->service_data['scope'],
            'GET',
            $this->service_data['lineitems'],
            null,
            null,
            'application/vnd.ims.lis.v2.lineitemcontainer+json'
        );
        foreach ($line_items['body'] as $line_item) {
            if ($line_item['tag'] == $new_line_item->get_tag()) {
                return new LTI_Lineitem($line_item);
            }
        }
        $created_line_item = $this->service_connector->make_service_request(
            $this->service_data['scope'],
            'POST',
            $this->service_data['lineitems'],
            strval($new_line_item),
            'application/vnd.ims.lis.v2.lineitem+json',
            'application/vnd.ims.lis.v2.lineitem+json'
        );
        return new LTI_Lineitem($created_line_item['body']);
    }

    public function get_grades(LTI_Lineitem $lineitem) {
        $lineitem = $this->find_or_create_lineitem($lineitem);
        $scores = $this->service_connector->make_service_request(
            $this->service_data['scope'],
            'GET',
            $lineitem->get_id() . '/results',
            null,
            null,
            'application/vnd.ims.lis.v2.resultcontainer+json'
        );

        return $scores['body'];
    }
}
?>