<?php

class Collection_Controller extends MY_Controller {

    function __construct() {
        parent::__construct();
        if ($this->auth->isGuest()) {
            $this->notice->error('Du måste vara inloggad för att kunna göra detta.');
            redirect();
        }
        $this->load->model('Collection');
        $this->load->model('Record');
        $this->history->exclude();
    }

    function delete($record = NULL) {
        $this->load->library('form_validation');
        if ($this->input->post('record') !== false) {
            $record = $this->input->post('record');
            $this->data['record'] = $this->Record->get($record, $this->auth->getUserID());
            $res = $this->Collection->deleteItem($record, $this->auth->getUserID());
            if ($res == 1) {
                $this->notice->success($this->data['record']->name . ' - ' . $this->data['record']->title . ' har tagits bort.');
            }
            redirect($this->history->pop());
        } else {
            $this->data['record'] = $this->Record->get($record, $this->auth->getUserID());
        }
    }

    function comment($record = null) {
        $this->load->library('form_validation');
        $this->load->model('Comment');
        if ($this->input->post('record')) {
            $record = $this->input->post('record');
        }
        $this->data['record'] = $this->Record->get($record, $this->auth->getUserID());
        if($this->input->post('action') == 'delete') {
            $this->Comment->delete($this->auth->getUserID(), $record);
            redirect($this->history->pop());
        } else if ($this->Comment->validateData() !== false) {
            $this->Comment->set($this->auth->getUserID(), $record, $this->input->post('comment'));
            redirect($this->history->pop());
        }
    }

    function record($id = 0) {
        if($id == 0) {
            $id = $this->input->post('id');
        }

        $this->load->library('form_validation');
        $this->form_validation->set_error_delimiters('<div class="error">', '</div>');
        $this->form_validation->set_rules('id', 'ID', 'required');
        $this->form_validation->set_rules('artist', 'Artist', 'required|max_length[64]|xss_clean');
        $this->form_validation->set_rules('title', 'Titel', 'required|max_length[150]|xss_clean');
        $this->form_validation->set_rules('year', 'År', 'is_natural_no_zero|exact_length[4]');
        $this->form_validation->set_rules('format', 'Format', 'max_length[30]|xss_clean');
        $this->form_validation->nonce();

        if($id > 0) {
            $this->data['record'] = $this->Record->get($id, $this->auth->getUserID());
        } else {
            $rec->id = 0;
            $rec->name = '';
            $rec->title = '';
            $rec->year = '';
            $rec->format = '';
            $this->data['record'] = $rec;
        }

        $this->data['id'] = $id;

        if ($this->form_validation->run() !== FALSE) { // If validation has completed
            $this->load->model('Artist');
            $this->load->model('Comment');
            if($id > 0) {
                $comment = $this->Comment->fetchOne($id)->comment;
                $this->Collection->deleteItem($id, $this->auth->getUserID());
            } else if($this->input->post('comment')) {
                $comment = $this->input->post('comment');
            }
            $artist_id = $this->Artist->getArtistId($this->input->post('artist'));
            $record_id = $this->Record->getId($artist_id, $this->input->post('title'),
                            $this->input->post('year'), $this->input->post('format'));
            $coll_id = $this->Collection->addItem($this->auth->getUserId(), $record_id);
            if(isset($comment)) {
                $this->Comment->set($this->auth->getUserID(), $coll_id, $comment);
            }
            $this->notice->success($this->input->post('artist') . ' - ' . $this->input->post('title')
                    . ' har ' . (($id == 0) ? 'lagts till' : 'uppdaterats') . '.');
            if($id == 0) {
                redirect('collection/record');
            } else {
                redirect($this->history->pop());
            }
        }
    }

	function import() {
		if ( isset($_FILES['userfile']) ) {
            if( $this->auth->getUser()->last_import !== null && $this->auth->getUser()->last_import > (time() - 60*60)) {
                $this->notice->error('Det har inte gått en timme sedan din senaste import.');
                redirect('collection/import');
            }
			$file = $_FILES['userfile'];
			if(substr($file['name'], -3) !== 'xml') {
				$this->notice->error('Endast XML-filer är tillåtna.');
				redirect('collection/import');
			}
			$reader = new XMLReader();
			$reader->open($file['tmp_name']);
			$active = false;
			$this->load->model('Artist');
            $this->load->model('Comment');
			$import_type = FALSE;
			while($reader->read()) {
				if($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'collection') {
					$import_type = 'skivsamlingen';
					break;
				} else if($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'recordcollection') {
					$import_type = 'pop.nu';
					break;
				}
			}
			if($import_type === FALSE) {
				$this->notice->error('XML-filen var inte korrekt formaterad.');
				redirect('users/'.$this->auth->getUser()->username);
			}
			$this->Collection->deleteAll($this->auth->getUser()->id);
			while($reader->read()) {
				if($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'record') {
					$active = true;
					$active = new SimpleXMLElement($reader->readOuterXml());
				} else if($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == 'record') {
					$data['artist'] = html_entity_decode((string)$active->artist);
					$data['title'] = html_entity_decode((string)$active->title);
					if($import_type == 'skivsamlingen') {
						$data['year'] = (isset($active->year) ? (string)$active->year : NULL);
						$data['format'] = (isset($active->format) ? (string)$active->format : NULL);
					} else {
						$data['year'] = (isset($active->year_release) ? (string)$active->year_release : NULL);
						$data['format'] = ($active->format == ' obestämt' ? NULL : (string)$active->format);
					}
					// VALIDATION
					if(!$this->form_validation->validate($data['artist'], array('required', 'max_length[64]'))) {
                        if ($data['artist'] && strlen($data['artist']) >= 64) {
                            $data['artist'] = mb_substr($data['artist'], 0, 64);
                        }
                    }
					if(!$this->form_validation->validate($data['title'], array('required', 'max_length[150]'))) {
                        if ($data['title'] && strlen($data['title']) >= 150) {
                            $data['title'] = mb_substr($data['title'], 0, 150);
                        }
                    }
					if( ! $this->form_validation->validate($data['year'],
                        array('is_natural_no_zero', 'exact_length[4]'))) {
                            $data['year'] = (int) $data['year'];
                            if(!$this->form_validation->validate($data['year'],
                                array('is_natural_no_zero', 'exact_length[4]'))) {
                                    $data['year'] = null;
                            }
                    }
					if(!$this->form_validation->validate($data['format'], 'max_length[30]')) {
                        if ($data['title'] && strlen($data['title']) >= 30) {
                            $data['title'] = mb_substr($data['title'], 0, 30);
                        }
                    }
					$data['artist'] = $this->form_validation->xss_clean($data['artist']);
					$data['title'] = $this->form_validation->xss_clean($data['title']);
					$data['format'] = $this->form_validation->xss_clean($data['format']);
					$artist_id = $this->Artist->getArtistId($data['artist']);
					$record_id = $this->Record->getId($artist_id, $data['title'],
                            $data['year'], $data['format']);
					$this->Collection->addItem($this->auth->getUserId(), $record_id);

					$active = false;
				}
			}
            $this->User->update($this->auth->getUserId(), array('last_import' => time()), false);
			$this->notice->success('Din XML-fil har importerats!');
			redirect('users/'.$this->auth->getUser()->username);
		} else {
			$this->data['user'] = $this->auth->getUser();
		}
	}

}

/* End of file user.php */
/* Location: ./system/application/controllers/user.php */