<?php
// Elak akses terus
if (!defined('ABSPATH')) {
    exit;
}

class PCE_GitHub_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $username;
    private $repository;
    private $info_url;

    public function __construct($file) {
        $this->file = $file;
        $this->username = 'kodeexii'; // GANTIKAN DENGAN USERNAME GITHUB DEN
        $this->repository = 'page-no-cache'; // GANTIKAN DENGAN NAMA REPO DEN

        // URL mentah ke fail info.json dalam repo GitHub
        $this->info_url = "https://raw.githubusercontent.com/{$this->username}/{$this->repository}/main/info.json";
        
        $this->plugin = get_plugin_data($file);
        $this->basename = plugin_basename($file);

        // Hook utama untuk menyemak update
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        // Hook untuk paparan 'View details'
        add_filter('plugins_api', array($this, 'plugin_api_info'), 10, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_info = $this->get_remote_info();
        if (!$remote_info) {
            return $transient;
        }

        // Bandingkan versi semasa dengan versi dari GitHub
        if (version_compare($this->plugin['Version'], $remote_info->version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->repository;
            $obj->plugin = $this->basename;
            $obj->new_version = $remote_info->version;
            $obj->url = $remote_info->homepage;
            $obj->package = $remote_info->download_url;
            $transient->response[$this->basename] = $obj;
        }

        return $transient;
    }

    public function plugin_api_info($res, $action, $args) {
        // Semak jika ini adalah permintaan info untuk plugin kita
        if ($action !== 'plugin_information' || $args->slug !== $this->repository) {
            return $res;
        }

        $remote_info = $this->get_remote_info();
        if (!$remote_info) {
            return $res;
        }

        $res = new stdClass();
        $res->name = $remote_info->name;
        $res->slug = $this->repository;
        $res->version = $remote_info->version;
        $res->author = $this->plugin['Author'];
        $res->homepage = $remote_info->homepage;
        $res->download_link = $remote_info->download_url;
        $res->sections = (array) $remote_info->sections;
        
        return $res;
    }

    private function get_remote_info() {
        $request = wp_remote_get($this->info_url);
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return false;
        }
        return json_decode(wp_remote_retrieve_body($request));
    }
}
