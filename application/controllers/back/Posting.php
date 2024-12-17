<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Posting extends CI_Controller {

    var $table = 'posting';
    var $tableJoin = 'category';
    var $id = 'id';
    var $select = ['posting.*', 'category.category_name AS category'];
    var $column_order = ['posting.id', 'posting.title', 'posting.featured', 'posting.choice', 'posting.thread', 'category.category_name', 'posting.is_active', 'posting.date'];
    var $column_search = ['posting.title', 'posting.seo_title', 'posting.featured', 'posting.choice', 'posting.thread', 'category.category_name', 'posting.is_active', 'posting.date'];

    public function __construct()
    {
        parent::__construct();
        // Aktifkan error reporting untuk debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Load model
        $this->load->model('my_model', 'my', true);
        $this->load->model('posting_model', 'posting', true);
        $this->load->model('menu_model', 'menu', true);
        $this->load->model('category_model', 'category', true);
    }

    public function ajax_list()
    {
        $list = $this->my->get_datatables($this->tableJoin, $this->select);
        $data = [];
        foreach ($list as $li) {
            $row = [];
            $row[] = '<input type="checkbox" class="data-check" value="' . $li->id . '">';
            $row[] = $li->title;
            $row[] = $li->featured;
            $row[] = $li->choice;
            $row[] = $li->thread;
            $row[] = $li->category;
            $row[] = $li->is_active;
            $row[] = $li->date;

            $row[] = 
            '<a class="btn btn-sm btn-warning text-white" href="' . base_url("back/posting/update/$li->id") . '" 
            title="Edit">
            <i class="fa fa-pencil-alt mr-1"></i></a>

            <a class="btn btn-sm btn-danger" href="#" 
            title="Delete" onclick="delete_posting(' . "'" . $li->id . "'" . ')">
            <i class="fa fa-trash mr-1"></i></a>';
            $data[] = $row;
        }

        $output = [
            'draw'            => $_POST['draw'],
            'recordsTotal'    => $this->my->count_all(),
            'recordsFiltered' => $this->my->count_filtered(),
            'data'            => $data
        ];

        echo json_encode($output);
    }

    public function update($id)
    {
        if (!$id) {
            show_error("ID tidak ditemukan!", 404);
        }

        $dataPost = $this->posting->getPostingById($id);

        if (!$dataPost) {
            $this->session->set_flashdata('warning', 'Maaf, data tidak dapat ditemukan!');
            redirect(base_url('admin/posting'));
        }

        $input = (!$_POST) ? $dataPost : (object) $this->input->post(null, true);

        // Form validation
        $this->form_validation->set_rules('title', 'Title', 'required');
        $this->form_validation->set_rules('content', 'Content', 'required');
        $this->form_validation->set_rules('id_category', 'Category', 'required');

        if ($this->form_validation->run() == false) {
            $data['title'] = 'Edit Posting';
            $data['form_action'] = base_url("back/posting/update/$id");
            $data['menu'] = $this->menu->getMenu();
            $data['category'] = $this->category->getCategory();
            $data['input'] = $input;
            $this->load->view('back/pages/article/form_post', $data);
        } else {
            $data = [
                'title' => $this->input->post('title', true),
                'seo_title' => slugify($this->input->post('title', true)),
                'content' => $this->input->post('content', true),
                'featured' => $this->input->post('featured', true),
                'choice' => $this->input->post('choice', true),
                'thread' => $this->input->post('thread', true),
                'id_category' => $this->input->post('id_category', true),
                'is_active' => $this->input->post('is_active', true),
                'date' => date('Y-m-d')
            ];

            // Proses upload foto jika ada
            if (!empty($_FILES['photo']['name'])) {
                $upload = $this->posting->uploadImage();
                if ($upload) {
                    $this->_create_thumbs($upload);
                    $posting = $this->my->get_by_id($id);

                    // Hapus gambar lama
                    $this->_delete_image($posting->photo);
                    $data['photo'] = $upload;
                }
            }

            $this->my->update(['id' => $id], $data);
            $this->session->set_flashdata('success', 'Posting Berhasil Diupdate.');

            redirect(base_url('admin/posting'));
        }
    }

    private function _delete_image($photo)
    {
        $paths = [
            "images/posting/$photo",
            "images/posting/large/$photo",
            "images/posting/medium/$photo",
            "images/posting/small/$photo",
            "images/posting/xsmall/$photo"
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && $photo) {
                unlink($path);
            }
        }
    }

    private function _create_thumbs($file_name)
    {
        $config = [
            ['width' => 770, 'height' => 450, 'new_image' => 'large'],
            ['width' => 300, 'height' => 188, 'new_image' => 'medium'],
            ['width' => 270, 'height' => 169, 'new_image' => 'small'],
            ['width' => 170, 'height' => 100, 'new_image' => 'xsmall']
        ];

        $this->load->library('image_lib');
        foreach ($config as $item) {
            $resize_config = [
                'image_library' => 'GD2',
                'source_image' => './images/posting/' . $file_name,
                'new_image' => "./images/posting/{$item['new_image']}/" . $file_name,
                'maintain_ratio' => FALSE,
                'width' => $item['width'],
                'height' => $item['height']
            ];

            $this->image_lib->initialize($resize_config);
            if (!$this->image_lib->resize()) {
                log_message('error', $this->image_lib->display_errors());
            }
            $this->image_lib->clear();
        }
    }
}
