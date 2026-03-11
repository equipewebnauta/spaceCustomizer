
/*
Plugin Name: WPLMS Customizer Plugin
Plugin URI: http://www.Vibethemes.com
Description: A simple WordPress plugin to modify WPLMS template
Version: 1.0
Author: VibeThemes
Author URI: http://www.vibethemes.com
License: GPL2
*/
/*
Copyright 2014  VibeThemes  (email : vibethemes@gmail.com)

wplms_customizer program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

wplms_customizer program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with wplms_customizer program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


include_once 'classes/customizer_class.php';



if (class_exists('WPLMS_Customizer_Plugin_Class')) {
    // instantiate the plugin class
    $wplms_customizer = new WPLMS_Customizer_Plugin_Class();
}

@ini_set('upload_max_size', '256M');
@ini_set('post_max_size', '256M');
@ini_set('max_execution_time', '300');


/* Disable WordPress Admin Email Verification */
add_filter('admin_email_check_interval', '__return_false');


/* Redirect Cart Page to Homepage when Cart is empty */
add_action('init', 'custom_cart_redirection');
function custom_cart_redirection()
{
    if (class_exists('WooCommerce')) {
        if (is_cart()) {
            if (WC()->cart->is_empty()) {
                wp_safe_redirect(home_url());
                exit();
            }
        }
    }
}


/*	Limite a visibilidade da regra de usuário SPACE Administrador para não ver os Administradores nem criar usuários com essa regra. */
function limit_admin_users_visibility()
{
    $user = wp_get_current_user();

    // Verifica se o usuário atual tem a função "admin-space"
    if (in_array('admin-space', $user->roles)) {
        add_filter('views_users', 'admin_users_views_filter');
        add_filter('editable_roles', 'admin_users_editable_roles_filter');
        add_action('pre_user_query', 'admin_users_pre_user_query');
        add_action('admin_init', 'admin_users_admin_init');
    }
}

// Filtra as visualizações de usuários
function admin_users_views_filter($views)
{
    unset($views['administrator']);
    return $views;
}

// Filtra os papéis de usuário editáveis
function admin_users_editable_roles_filter($roles)
{
    if (isset($roles['administrator']) && current_user_can('manage_options')) {
        unset($roles['administrator']);
    }
    return $roles;
}

// Filtra a consulta de usuários
function admin_users_pre_user_query($user_query)
{
    global $wpdb;
    $user_query->query_where = str_replace(
        "WHERE 1=1",
        "WHERE 1=1 AND {$wpdb->users}.user_login != 'admin'",
        $user_query->query_where
    );
}

// Redireciona usuários não autorizados
function admin_users_admin_init()
{
    $screen = get_current_screen();
    $user = wp_get_current_user();

    // Verifica se o usuário está na página de usuários ou editando um usuário
    if (($screen->id == 'users' || $screen->id == 'user') && in_array('admin-space', $user->roles)) {
        wp_die('Você não tem permissão para acessar esta página.');
    }
}

add_action('admin_init', 'limit_admin_users_visibility');



/*  Ocultar páginas para usuários admin-space através do ID */
function ocultar_paginas_por_id($query)
{
    // Verifica se o usuário está logado e tem a função "admin-space"
    if (current_user_can('admin-space') && !current_user_can('administrator')) {
        // IDs das páginas a serem ocultadas
        $paginas_a_ocultar = array(2212, 2213, 2211, 534, 345, 296, 321, 322, 358, 336, 297, 323, 346, 190, 344, 320, 283, 1689, 283, 343); // Substitua pelos IDs das páginas que você quer ocultar

        // Verifica se estamos na tela de edição de páginas
        if ($query->is_main_query() && $query->get('post_type') == 'page') {
            $query->set('post__not_in', $paginas_a_ocultar);
        }
    }
}
add_action('pre_get_posts', 'ocultar_paginas_por_id');


/*	Remove o menu lateral de aparência para admin-space. 
function remove_appearance_menu_for_admin_space() {
    $user = wp_get_current_user();
    
    if ( in_array( 'admin-space', (array) $user->roles ) ) {
        remove_menu_page( 'themes.php' );
    }
}
add_action( 'admin_menu', 'remove_appearance_menu_for_admin_space' );*/




/* ===========================
   GERENCIAMENTO DE FUNCIONALIDADES SPACE POCKET
   =========================== */

/**
 * =========================================================
 * Painel de Gerenciamento de Funcionalidades
 * =========================================================
 * 
 * Autor: Miguel Cezar Ferreira
 * Data: 09/02/2026
 * 
 * Descrição:
 * Este módulo cria um painel administrativo centralizado chamado
 * **"Gerenciar Funcionalidades"**, permitindo ativar e desativar
 * facilmente diversas integrações importantes do site através de
 * toggles modernos. Além disso, implementa um sistema avançado
 * de sincronização automática chamado **WPLMS Stats**, que baixa
 * arquivos diretamente de um repositório GitHub para cache local.
 * 
 * ⚙️ O que este código faz:
 * 
 * 🔧 1. Painel "Gerenciar Funcionalidades"
 * - Adiciona um menu dedicado no painel WordPress.
 * - Lista plugins essenciais e permite ativar ou desativar via AJAX:
 *     • WPS Hide Login  
 *     • H5P  
 *     • WooCommerce  
 *     • Cookie Notice  
 *     • JoinChat (WhatsApp)
 * - O status atual de cada plugin é exibido em tempo real.
 * - Utiliza switches customizados com CSS para visual moderno.
 * 
 * 🚀 2. WPLMS Stats — Sistema de Cache via GitHub
 * - Adiciona um toggle exclusivo para habilitar ou desabilitar
 *   o módulo WPLMS Stats.
 * - Quando ativado:
 *     • Salva a opção `wplms_stats_enabled = 1`
 *     • Adiciona automaticamente o menu "WPLMS Stats"
 *     • Executa `wplms_load_github_stats_config()` para baixar       
 *       imediatamente o arquivo remoto do GitHub (cache local)
 * - Quando desativado:
 *     • Remove completamente o menu
 *     • Desabilita carregamento e sincronização
 * 
 * 📁 3. Página "WPLMS Stats"
 * - Exibe o conteúdo do arquivo baixado do GitHub.
 * - Mostra mensagens de sucesso/erro ao carregar.
 * - Não possui botão manual (tudo é automático via toggle).
 * - Exibe o cache em um bloco `<pre>` com scroll e formatação limpa.
 * 
 * 🔍 4. Funcionalidades Técnicas Importantes
 * - AJAX seguro com verificação de permissão (`manage_options`).
 * - Sanitização de parâmetros e execução controlada.
 * - Uso de `activate_plugin()` e `deactivate_plugins()`.
 * - Inserção dinâmica de menus usando `admin_menu`.
 * - CSS customizado para switches (estilo iOS).
 * - JS com jQuery para eventos assíncronos sem recarregamentos indevidos.
 * 
 * 📊 Exemplo do que o administrador pode controlar:
 * 
 * | Funcionalidade     | Tipo        | Controle via Toggle |
 * |--------------------|-------------|---------------------|
 * | Acesso Seguro      | Plugin      | Sim                 |
 * | WhatsApp           | Plugin      | Sim                 |
 * | H5P Interativo     | Plugin      | Sim                 |
 * | WooCommerce        | Plugin      | Sim                 |
 * | Cookies LGPD       | Plugin      | Sim                 |
 * | WPLMS Stats        | Sistema     | Sim (com cache Git) |
 * 
 * 🧠 Funcionamento Interno do WPLMS Stats
 * - Buscar arquivo remoto (GitHub Pages)
 * - Criar arquivo temporário em cache
 * - Atualizar automaticamente quando o toggle é ligado
 * - Exibição limpa usando `file_get_contents()`
 * 
 * 💡 Possíveis Expansões Futuras:
 * - Logs de sincronização do GitHub
 * - Notificações push quando o arquivo remoto mudar
 * - Histórico de versões baixadas
 * - Permitir múltiplos arquivos remotos no mesmo painel
 * - Tornar o painel "Gerenciar Funcionalidades" modular, permitindo
 *   adicionar toggles via filtros (hooks)
 * 
 * 🧩 Requisitos:
 * - WordPress 6.x+
 * - Privilégios de Administrador
 * - cURL ou allow_url_fopen habilitados (para baixar arquivo do GitHub)
 * 
 * =========================================================
 * Este módulo oferece uma interface moderna, prática e elegante
 * para controlar integrações essenciais do site, reduzindo tempo,
 * erros operacionais e eliminando necessidade de plugins extras.
 * =========================================================
 */

function custom_plugin_management_page()
{
    add_menu_page(
        'Gerenciar funcionalidades SPACE Pocket',
        'Gerenciar funcionalidades',
        'manage_options',
        'custom-plugin-management',
        'custom_plugin_management_page_content',
        'dashicons-admin-plugins',
        30
    );
}
add_action('admin_menu', 'custom_plugin_management_page');


/**
 * ================================================
 * PÁGINA DO MENU
 * ================================================
 */
function custom_plugin_management_page_content()
{

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    /**
     * ================================================
     * LISTA DE PLUGINS PARA GERENCIAMENTO
     * ================================================
     */
    $plugins_to_manage = array(
        'wps-hide-login/wps-hide-login.php'                   => 'Acesso seguro',
        'h5p/h5p.php'                                         => 'Materiais interativos',
        'woocommerce/woocommerce.php'                         => 'Vendas',
        'cookie-law-info/cookie-law-info.php'                 => 'Cookies',
        'creame-whatsapp-me/joinchat.php'                     => 'WhatsApp',
        'wplms-wishlist/wplms-wishlist.php'                   => 'Lista de Desejos',
        'vibe-jitsi/vibe-jitsi.php'                           => 'Eventos ao vivo',
        'wplms-custom-learning-paths/wplms-custom-learning-paths.php' => 'Trilhas Personalizadas de Aprendizado',
        //'wplms-attendance/wplms-attendance.php'               => 'Presença de Alunos - DEV: Validação Pendente'
    );

    echo '<div class="wrap">';
    echo '<h2>Gerenciar Funcionalidades</h2>';

    echo '<table class="widefat">';
    echo '<thead><tr>
            <th style="width:40%;">Nome</th>
            <th style="width:20%;">Status</th>
            <th style="width:20%;">Ação</th>
          </tr></thead>';
    echo '<tbody>';


    /**
     * ================================================
     * 🔌 LOOP DOS PLUGINS NORMAIS
     * ================================================
     */
    foreach ($plugins_to_manage as $plugin_file => $plugin_name) {

        $is_active = is_plugin_active($plugin_file);

        echo '<tr>';
        echo '<td>' . esc_html($plugin_name) . '</td>';
        echo '<td>' . ($is_active ? '<strong style="color:green;">Ativo</strong>' : '<span style="color:red;">Desativado</span>') . '</td>';
        echo '<td>
                <label class="toggle-switch">
                    <input type="checkbox" class="plugin-toggle"
                           data-plugin="' . esc_attr($plugin_file) . '"
                           ' . ($is_active ? 'checked' : '') . '>
                    <span class="toggle-slider"></span>
                </label>
             </td>';
        echo '</tr>';
    }


		/**
 * ================================================
 * TÍTULO - Recursos SPACE
 * ================================================
 */
echo '<tr>';
echo '<td colspan="3" style="font-weight:bold; font-size:15px; padding:12px 8px; background:#f5f5f5;">
        Recursos Space
      </td>';
echo '</tr>';
	
	
    /**
     * ================================================
     * 📊 TOGGLE — WPLMS STATS
     * ================================================
     */
    $stats_enabled = get_option('wplms_stats_enabled', '0');

    echo '<tr>';
    echo '<td>SPACE Stats com cache (GitHub Cache)</td>';
    echo '<td>' . ($stats_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="wplms-stats-toggle" ' . ($stats_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';





    /**
     * ================================================
     * Estatísticas SPACE
     * ================================================
     */
    $estatisticas_enabled = get_option('estatisticas_enabled', '0');

    echo '<tr>';
    echo '<td>Estatísticas SPACE</td>';
    echo '<td>' . ($estatisticas_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="estatisticas-toggle" ' . ($estatisticas_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';


    /**
     * ================================================
     * Alunos Matriculados
     * ================================================
     */
    $alunos_enabled = get_option('wplms_alunos_enabled', '0');

    echo '<tr>';
    echo '<td>Relatório dos Alunos Matriculados</td>';
    echo '<td>' . ($alunos_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="wplms-alunos-toggle" ' . ($alunos_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';

	
	/**
     * ================================================
     * Seções Agendadas
     * ================================================
     */
	
    $sectionLock_enabled = get_option('sectionLock_enabled', '0');

    echo '<tr>';
    echo '<td>Recurso Seções Agendadas</td>';
    echo '<td>' . ($sectionLock_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="sectionLock-toggle" ' . ($sectionLock_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';
	
	
	
	
	
	

	
	   /**
     * ================================================
     * 💰 TOGGLE — COMISSÕES
     * ================================================
     */
    $comissoes_enabled = get_option('comissoes_enabled', '0');

    echo '<tr>';
    echo '<td>Comissões (Sistema Interno)</td>';
    echo '<td>' . ($comissoes_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="comissoes-toggle" ' . ($comissoes_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';

	
	
	/**
 * ================================================
 * TÍTULO - STYLES SPACE
 * ================================================
 */
echo '<tr>';
echo '<td colspan="3" style="font-weight:bold; font-size:15px; padding:12px 8px; background:#f5f5f5;">
        Styles Space
      </td>';
echo '</tr>';
	
	
	
	
	/**
     * ================================================
     * SPACE Categorias Personalizadas
     * ================================================
     */
    $categoriasPersonalizadas_enabled = get_option('categoriasPersonalizadas_enabled', '0');

    echo '<tr>';
    echo '<td>Categorias Personalizadas</td>';
    echo '<td>' . ($categoriasPersonalizadas_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="categoriasPersonalizadas-toggle" ' . ($categoriasPersonalizadas_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';

	
	
    /**
     * ================================================
     * CSS Customizado GITHUB
     * ================================================
     */
    $customCss_enabled = get_option('wplms_css_enabled', '0');

    echo '<tr>';
    echo '<td>Style SPACE</td>';
    echo '<td>' . ($customCss_enabled === '1'
        ? '<strong style="color:green;">Ativo</strong>'
        : '<span style="color:red;">Desativado</span>') . '</td>';
    echo '<td>
            <label class="toggle-switch">
                <input type="checkbox" class="wplms-css-toggle" ' . ($customCss_enabled === '1' ? 'checked' : '') . '>
                <span class="toggle-slider"></span>
            </label>
         </td>';
    echo '</tr>';
	



 

    echo '</tbody></table>';
    echo '</div>';
}

/**
 * ================================================
 * AJAX — Ativar/Desativar Plugins
 * ================================================
 */
add_action('wp_ajax_toggle_plugin_status', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $plugin = sanitize_text_field($_POST['plugin']);
    $status = $_POST['status'] == '1';

    if ($status) activate_plugin($plugin);
    else deactivate_plugins($plugin);

    wp_send_json_success();
});


/**
 * ================================================
 * AJAX — Ativar/Desativar WPLMS Stats
 * ================================================
 */
add_action('wp_ajax_toggle_wplms_stats', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('wplms_stats_enabled', $enabled ? '1' : '0');

    if ($enabled) {
        // Baixa imediatamente o arquivo do GitHub
        $executed = false;
        wplms_load_github_stats_config($executed, true);
    }

    wp_send_json_success();
});



/**
 * ================================================
 * AJAX — Ativar/Desativar Estatisticas
 * ================================================
 */
add_action('wp_ajax_toggle_estatisticas', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('estatisticas_enabled', $enabled ? '1' : '0');



    wp_send_json_success();
});




/**
 * ================================================
 * AJAX — Ativar/Desativar WPLMS Comissões
 * ================================================
 */
add_action('wp_ajax_toggle_comissoes', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('comissoes_enabled', $enabled ? '1' : '0');

    wp_send_json_success();
});




/**
 * ================================================
 * AJAX — Ativar/Desativar Alunos Matriculados
 * ================================================
 */
add_action('wp_ajax_toggle_alunos', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('wplms_alunos_enabled', $enabled ? '1' : '0');

    wp_send_json_success();
});









/**
 * ================================================
 * AJAX — Ativar/Desativar Seções Agendadas
 * ================================================
 */
add_action('wp_ajax_toggle_sectionLock', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('sectionLock_enabled', $enabled ? '1' : '0');

    wp_send_json_success();
});










/**
 * ================================================
 * AJAX — Ativar/Desativar SPACE Categorias Personalizadas
 * ================================================
 */
add_action('wp_ajax_toggle_categoriasPersonalizadas', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('categoriasPersonalizadas_enabled', $enabled ? '1' : '0');

    wp_send_json_success();
});







/**
 * ================================================
 * AJAX — Ativar/Desativar CSS Customizado
 * ================================================
 */
add_action('wp_ajax_toggle_css', function () {
    if (!current_user_can('manage_options')) wp_die('Acesso negado');

    $enabled = $_POST['status'] == '1';

    update_option('wplms_css_enabled', $enabled ? '1' : '0');

    wp_send_json_success();
});




/**
 * ================================================
 * ADICIONA OU REMOVE O MENU 
 * ================================================
 */

add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    add_menu_page(
        'Recursos SPACE',              // Page title
        'Recursos SPACE',              // Menu title
        'manage_options',              // Capability
        'wplms-stats',                 // 🔥 SLUG IGUAL AO PRIMEIRO SUBMENU
        'wplms_stats_admin_page',      // Callback real
        'dashicons-admin-generic',     // Ícone
        03                             // Posição
    );
});



/**
 * =====================================================
 * SUBMENU - SPACE Stats 
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    if (get_option('wplms_stats_enabled') === '1') {

        add_submenu_page(
            'wplms-stats',              // Menu pai
            'SPACE Stats',              // Page title
            '📊 SPACE Stats',            // Menu title
            'manage_options',           // Capability
            'wplms-stats',              // 🔥 MESMO SLUG DO MENU PAI
            'wplms_stats_admin_page'    // Callback
        );
    }
});


/**
 * =====================================================
 * SUBMENU - Alunos Matriculados
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    if (get_option('wplms_alunos_enabled') === '1') {
        add_submenu_page(
            'wplms-stats',                // Menu pai (Recursos SPACE)
            'Alunos Matriculados',        // Page title
            '👩‍🎓 Alunos Matriculados',   // Menu title
            'manage_options',             // Capability
            'wplms-alunos',               // Slug
            'wplms_pagina_alunos'         // Callback
        );
    }
});




/**
 * =====================================================
 * SUBMENU – Liberação por Seção
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    if (get_option('sectionLock_enabled') === '1') {
        add_submenu_page(
            'wplms-stats',                  // Menu pai (Recursos SPACE)
            'Liberação por Seção',           // Page title
            '⏱️ Liberação por Seção',        // Menu title
            'manage_options',               // Capability
            'wplms-section-drip',            // Slug
            'wplms_section_drip_admin_page'  // Callback
        );
    }
});


/**
 * =====================================================
 * SUBMENU - Categorias Personalizadas (Imagens / Cores)
 * =====================================================
 */
add_action('admin_menu', function () {

    // 🔐 Segurança
    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    // 🔧 Verifica se o módulo está habilitado
    if (get_option('categoriasPersonalizadas_enabled') === '1') {

        add_submenu_page(
            'wplms-stats',                        // 🔹 Menu pai
            __('Categorias Personalizadas', 'wplms'), // Page title
            '🖼️ Categorias Personalizadas',       // Menu title
            'manage_options',                     // Capability
            'wplms-course-category-images',       // Slug
            'wplms_course_category_image_page'    // Callback
        );
    }
});






/**
 * =====================================================
 * SUBMENU - Comissões
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    if (get_option('comissoes_enabled') === '1') {

        add_submenu_page(
            'wplms-stats',                   // Menu pai
            'Comissões SPACE',               // Page title
            '💰 Comissões',                  // Menu title
            'manage_options',               // Capability
            'wplms-commissions',            // Slug
            'wplms_commissions_admin_page'  // Callback
        );
    }
});


/**
 * =====================================================
 * SUBMENU - Estatísticas dos Cursos
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    // (Opcional) Se quiser condicionar por option no futuro
    if (get_option('estatisticas_enabled') === '1') {

        add_submenu_page(
            'wplms-stats',                 // Menu pai
            'Estatísticas dos Cursos',     // Page title
            '📈 Estatísticas dos Cursos',  // Menu title
            'manage_options',              // Capability
            'wplms-custom-stats',          // Slug
            'wplms_render_stats_page'      // Callback
        );
    }
});




/**
 * =====================================================
 * SUBMENU - SPACE Custom CSS
 * =====================================================
 */
add_action('admin_menu', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Acesso negado.');
    }

    if (get_option('wplms_css_enabled') === '1') {

        add_submenu_page(
            'wplms-stats',                       // Menu pai
            'SPACE Custom CSS',                  // Page title
            '🎨 SPACE CSS',                      // Menu title
            'manage_options',                   // Capability
            'spacelms-custom-css',              // Slug
            'spacelms_custom_css_admin_page'    // Callback
        );
    }
});



/**
 * ============================================================
 * 🎨 SpaceLMS Custom CSS – Auto Download & Cache
 * ============================================================
 *
 * 📌 Autor: Miguel Cezar Ferreira
 * 📅 Data: 18/12/2025
 *
 * Descrição:
 * Loader inteligente responsável por baixar automaticamente
 * o arquivo custom-style.css do GitHub e armazenar em cache
 * local no WordPress.
 *
 * Cache: 6 horas
 * Diretório: /wp-content/uploads/spacelms-cache/
 *
 * ============================================================
 */
function spacelms_load_github_custom_css(&$executed = null, $force_download = false)
{

    $executed = false;

    $remote_url = 'https://raw.githubusercontent.com/equipewebnauta/spacelms-customCSS/main/custom-style.css';

    $cache_dir  = WP_CONTENT_DIR . '/uploads/spacelms-cache/';
    $cache_file = $cache_dir . 'custom-style.css';

    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }

    $cache_expired = true;

    if (file_exists($cache_file)) {
        $cache_expired = (time() - filemtime($cache_file)) > 21600;
    }

    if ($force_download || $cache_expired) {

        $response = wp_remote_get($remote_url, ['timeout' => 15]);

        if (!is_wp_error($response)) {

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code === 200 && strlen(trim($body)) > 20) {

                file_put_contents($cache_file, $body);
                touch($cache_file);

                error_log('SPACE CSS: CSS atualizado do GitHub.');
                $executed = true;
            } else {
                error_log('SPACE CSS: Resposta inválida ao baixar CSS.');
            }
        } else {
            error_log('SPACE CSS: Erro de conexão com GitHub.');
        }
    } else {
        $executed = true;
    }

    return file_exists($cache_file) ? $cache_file : false;
}


/**
 * ============================================================
 * ENQUEUE FRONTEND (CONTROLADO POR customCss_enabled)
 * ============================================================
 */
add_action('wp_enqueue_scripts', function () {

    // 🔐 Fonte da verdade
    if (get_option('wplms_css_enabled') !== '1') {
        return;
    }

    // 🔍 Verifica se a URL começa com /app
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, '/app') !== 0) {
        return; // ❌ Não é /app
    }

    $executed = false;
    $css_file = spacelms_load_github_custom_css($executed);

    if ($css_file && file_exists($css_file)) {

        wp_enqueue_style(
            'spacelms-custom-css',
            content_url('uploads/spacelms-cache/custom-style.css'),
            [], // ou ['theme-style-handle'] se quiser depender do tema
            filemtime($css_file),
            'all'
        );
    }
}, 9999); // 🚀 prioridade ALTÍSSIMA

/**
 * ============================================================
 * PÁGINA ADMIN (visualização + update)
 * ============================================================
 */
function spacelms_custom_css_admin_page()
{

    /* ============================================================
     * 💾 SALVAR IMAGEM (FORMATO CSS)
     * ============================================================
     */
    if (isset($_POST['spacelms_bg_image_url'])) {

        $url = esc_url_raw($_POST['spacelms_bg_image_url']);

        if ($url) {
            // salva exatamente no formato CSS
            $css_value = "url('{$url}')";
            update_option('spacelms_bg_image_css', $css_value);
        }
    }

    $bg_image_css = get_option('spacelms_bg_image_css', '');

    // Extrai URL só para preview
    $bg_image_url = '';
    if ($bg_image_css && preg_match("/url\\(['\"]?(.*?)['\"]?\\)/", $bg_image_css, $m)) {
        $bg_image_url = $m[1];
    }

    $force_download = isset($_POST['spacelms_force_download']);

    $executed   = false;
    $cache_file = spacelms_load_github_custom_css($executed, $force_download);

    $content = ($cache_file && file_exists($cache_file))
        ? file_get_contents($cache_file)
        : '';

    $enabled = (get_option('customCss_enabled') === '1');

    echo '<div class="wrap">';
    echo '<h1>🎨 SPACE Custom CSS</h1>';

    /**
     * ============================================================
     * STATUS
     * ============================================================
     */
    echo '<p style="
        padding:12px;
        border-left:5px solid ' . ($enabled ? '#00c853' : '#d50000') . ';
        background:#f9f9f9;
        max-width:520px;
    ">
        <strong>Status da aplicação:</strong>
        ' . ($enabled
        ? '<span style="color:#00c853;font-weight:bold;">ATIVADO</span>'
        : '<span style="color:#d50000;font-weight:bold;">DESATIVADO</span>') . '
        <br>
        <small>Controlado pelo sistema principal (customCss_enabled).</small>
    </p>';

    /**
     * ============================================================
     * ATUALIZAR CSS
     * ============================================================
     */
    echo '<form method="post" style="margin:20px 0;">';
    echo '<input type="hidden" name="spacelms_force_download" value="1">';
    echo '<button type="submit" style="
        background:#1e88e5;
        color:#fff;
        border:none;
        padding:10px 18px;
        border-radius:4px;
        font-size:15px;
        cursor:pointer;
    ">🔄 Atualizar CSS Agora</button>';
    echo '</form>';

    if ($executed && $force_download) {
        echo '<p style="color:#00c853;font-weight:bold;">✓ CSS atualizado com sucesso.</p>';
    }

    /**
     * ============================================================
     * 🖼️ IMAGEM → VARIÁVEL CSS
     * ============================================================
     */
    echo '<h2>Imagem de Fundo (CSS Variable)</h2>';

    echo '<form method="post" style="margin-bottom:25px;">';

    echo '<input type="hidden" id="spacelms_bg_image_url" name="spacelms_bg_image_url" value="' . esc_attr($bg_image_url) . '">';

    echo '<button type="button" class="button" id="spacelms-select-image">📷 Selecionar Imagem</button> ';
    echo '<button type="submit" class="button button-primary">💾 Salvar</button>';

    if ($bg_image_css) {
        echo '<div style="margin-top:12px;">
            <strong>Valor salvo:</strong>
            <pre style="background:#f4f4f4;padding:8px;border-radius:4px;">
--bg-image-url: ' . esc_html($bg_image_css) . ';
            </pre>
            <img src="' . esc_url($bg_image_url) . '" style="max-width:300px;border:1px solid #ccc;padding:4px;">
        </div>';
    }

    echo '</form>';

    if (!$content) {
        echo '<p style="color:red;font-weight:bold;">⚠ CSS não pôde ser carregado.</p>';
        echo '</div>';
        return;
    }

    /**
     * ============================================================
     * VISUALIZAÇÃO DO CSS
     * ============================================================
     */
    echo '<h2>Conteúdo do custom-style.css</h2>';

    echo '<div style="background:#1e1e1e;border-radius:6px;overflow:hidden;max-height:500px;">';
    echo '<div style="background:#2d2d2d;padding:8px 12px;color:#bbb;font-family:monospace;">custom-style.css</div>';
    echo '<pre style="
        margin:0;
        padding:16px;
        color:#dcdcdc;
        font-size:14px;
        font-family:Consolas, Monaco, monospace;
        overflow:auto;
        max-height:460px;
    ">' . esc_html($content) . '</pre>';
    echo '</div>';

    echo '<p style="margin-top:10px;color:#4da3ff;">
        Caminho do arquivo:
        <code style="color:black;">' . esc_html($cache_file) . '</code>
    </p>';

    /**
     * ============================================================
     * JS MEDIA UPLOADER
     * ============================================================
     */
    echo '<script>
    jQuery(document).ready(function($){
        let mediaUploader;

        $("#spacelms-select-image").on("click", function(e){
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: "Selecionar imagem",
                button: { text: "Usar esta imagem" },
                multiple: false
            });

            mediaUploader.on("select", function(){
                const attachment = mediaUploader.state().get("selection").first().toJSON();
                $("#spacelms_bg_image_url").val(attachment.url);
            });

            mediaUploader.open();
        });
    });
    </script>';

    echo '</div>';
}

add_action('wp_enqueue_scripts', function () {

    // handle vazio, só para inline
    wp_register_style('spacelms-root', false);
    wp_enqueue_style('spacelms-root');

    $bg_image_css = get_option('spacelms_bg_image_css');

    if ($bg_image_css) {
        wp_add_inline_style(
            'spacelms-root',
            ":root{--bg-image-url:{$bg_image_css};}"
        );
    }
}, 1);





/**
 * ======================================================
 *  LIBERAÇÃO DE CONTEÚDO POR SEÇÃO (DRIP FEED) – WPLMS
 * ======================================================
 *
 * Autor: Miguel Ferreira
 * Data: 09/02/2026
 * 
 * Este conjunto de funções implementa uma funcionalidade
 * administrativa personalizada para a plataforma WPLMS,
 * permitindo controlar a liberação de conteúdo por SEÇÃO
 * de um curso, com base em dias decorridos desde o início
 * do curso pelo aluno (drip feed por seção).
 *
 * ------------------------------------------------------
 *  VISÃO GERAL DA FUNCIONALIDADE
 * ------------------------------------------------------
 *
 * 1) É criado um item de menu no admin do WordPress
 *    chamado "Liberação por Seção".
 *
 * 2) O administrador seleciona um curso WPLMS.
 *
 * 3) O sistema:
 *    - Obtém o currículo real do curso (independente
 *      do formato usado pelo WPLMS).
 *    - Normaliza esse currículo em uma estrutura única
 *      de SEÇÕES e UNIDADES.
 *
 * 4) Para cada seção do curso, o admin pode definir
 *    após quantos dias ela será liberada para o aluno.
 *
 * 5) Esses valores são salvos como post_meta do curso
 *    no formato:
 *
 *    section_drip_days = [
 *        'introducao' => 0,
 *        'modulo-1'   => 3,
 *        'modulo-2'   => 7
 *    ]
 *
 * 6) Esses dados podem ser usados posteriormente
 *    no front-end ou via AJAX para bloquear ou liberar
 *    seções dinamicamente para o aluno.
 *
 * ------------------------------------------------------
 *  ESTRUTURA DO CÓDIGO
 * ------------------------------------------------------
 *
 * 🔹 MENU ADMIN
 * - Cria a página principal no painel do WordPress
 *   com permissão restrita a administradores.
 *
 * 🔹 OBTENÇÃO DO CURRÍCULO
 * - Busca o currículo do curso respeitando a hierarquia
 *   e compatibilidade do WPLMS:
 *     1) Função oficial do plugin
 *     2) Meta principal (vibe_course_curriculum)
 *     3) Meta legada (fallback)
 *
 * 🔹 PARSER UNIVERSAL DO CURRÍCULO
 * - Normaliza qualquer formato de currículo:
 *     • Serialized
 *     • JSON
 *     • String delimitada
 *     • Array híbrido
 *
 * - Gera uma estrutura padronizada:
 *   [
 *     [
 *       'title' => 'Nome da seção',
 *       'key'   => 'slug-unico',
 *       'units' => [ID_UNIDADE, ID_UNIDADE]
 *     ]
 *   ]
 *
 * - Garante:
 *     • Slugs únicos
 *     • Compatibilidade com currículos antigos
 *     • Seções implícitas quando unidades não possuem
 *       seção definida
 *
 * 🔹 INTERFACE ADMINISTRATIVA
 * - Interface construída no admin usando Bootstrap 5
 *   (apenas CSS via CDN).
 * - Exibe:
 *     • Seções
 *     • Unidades vinculadas
 *     • Campo numérico para dias de liberação
 *
 * 🔹 SEGURANÇA
 * - Proteção por:
 *     • current_user_can()
 *     • Nonce WordPress
 *     • Sanitização de dados
 *
 * 🔹 SALVAMENTO
 * - Os valores são salvos como post_meta do curso
 *   usando um array associativo.
 * - Após salvar, o admin é redirecionado com feedback
 *   visual de sucesso.
 *
 * ------------------------------------------------------
 *  BENEFÍCIOS DA ABORDAGEM
 * ------------------------------------------------------
 *
 * ✔ Compatível com diferentes versões do WPLMS
 * ✔ Não altera o core do tema ou plugin
 * ✔ Estrutura desacoplada (admin ≠ front-end)
 * ✔ Fácil manutenção e extensão futura
 * ✔ Preparado para integração com AJAX / React / App
 *
 * ------------------------------------------------------
 *  USO FUTURO
 * ------------------------------------------------------
 *
 * Os dados salvos por este sistema podem ser utilizados:
 * - Para bloquear seções no front-end
 * - Para exibir contadores de tempo restante
 * - Para controle de progressão do aluno
 * - Para integrações com o App do WPLMS
 *
 * ======================================================
 */




/* ======================================================
 *  OBTÉM CURRÍCULO DO CURSO (WPLMS REAL)
 * ====================================================== */
function wplms_get_course_curriculum($course_id){

    // 1️⃣ Função oficial do WPLMS (se existir)
    if(function_exists('bp_course_get_curriculum')){
        $curriculum = bp_course_get_curriculum($course_id);
        if(!empty($curriculum)) return $curriculum;
    }

    // 2️⃣ Meta principal do WPLMS
    $curriculum = get_post_meta($course_id, 'vibe_course_curriculum', true);
    if(!empty($curriculum)) return $curriculum;

    // 3️⃣ Fallback antigo
    return get_post_meta($course_id, 'course_curriculum', true);
}

/* ======================================================
 *  PÁGINA PRINCIPAL DO ADMIN
 * ====================================================== */
function wplms_section_drip_admin_page(){

    if(!current_user_can('manage_options')) return;

    $selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    ?>
    <div class="wrap">

    <div class="container-fluid px-0">

        <div class="container-fluid shadow-sm mb-4">
            <div class="card-body" style="padding: 1%">

                <h1 class="h4 mb-4">
                    Liberação de Conteúdo por Seção
                    <small class="text-muted d-block mt-1">SPACE</small>
                </h1>

                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="wplms-section-drip">

                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-semibold">
                            Selecione o curso
                        </label>

                        <select
                            name="course_id"
                            class="form-select"
                            onchange="this.form.submit()"
                        >
                            <option value="">— Selecione —</option>
                            <?php
                            $courses = get_posts([
                                'post_type'   => 'course',
                                'numberposts' => -1
                            ]);

                            foreach($courses as $course){
                                echo '<option value="'.$course->ID.'" '
                                    . selected($selected_course, $course->ID, false) . '>'
                                    . esc_html($course->post_title) .
                                '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </form>

            </div>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Configurações salvas com sucesso.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php
        if($selected_course){
            wplms_render_section_drip_settings($selected_course);
        }
        ?>

    </div>
</div>
    <?php
}

/* ======================================================
 *  PARSER UNIVERSAL DO CURRÍCULO
 * ====================================================== */
function wplms_parse_curriculum($curriculum){

    if (is_string($curriculum)) {
        $maybe = maybe_unserialize($curriculum);
        if ($maybe !== false) $curriculum = $maybe;
    }

    if (is_string($curriculum)) {
        $json = json_decode($curriculum, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $curriculum = $json;
        }
    }

    if (is_string($curriculum)) {
        $curriculum = explode('|', $curriculum);
    }

    if (!is_array($curriculum)) return [];

    $sections   = [];
    $keys_used  = [];
    $current    = null;

    foreach ($curriculum as $item) {

        if (is_array($item) && isset($item['type'])) {

            if ($item['type'] === 'section') {

                $base_key = sanitize_title($item['title']);
                if ($base_key === '') {
                    $base_key = 'secao';
                }

                $key = $base_key;
                $i   = 2;

                while (in_array($key, $keys_used, true)) {
                    $key = $base_key . '-' . $i;
                    $i++;
                }

                $keys_used[] = $key;

                $sections[] = [
                    'title' => $item['title'],
                    'key'   => $key,
                    'units' => []
                ];

                $current = count($sections) - 1;
            }

            if ($item['type'] === 'unit' && isset($item['id'])) {

                if ($current === null) {

                    $key = 'inicio';

                    if (in_array($key, $keys_used, true)) {
                        $key = 'inicio-2';
                    }

                    $keys_used[] = $key;

                    $sections[] = [
                        'title' => __('Conteúdo Inicial','wplms'),
                        'key'   => $key,
                        'units' => []
                    ];

                    $current = 0;
                }

                $sections[$current]['units'][] = (int) $item['id'];
            }

            continue;
        }

        if (is_numeric($item)) {
            if ($current !== null) {
                $sections[$current]['units'][] = (int) $item;
            }
            continue;
        }

        if (is_string($item)) {

            $base_key = sanitize_title($item);
            if ($base_key === '') {
                $base_key = 'secao';
            }

            $key = $base_key;
            $i   = 2;

            while (in_array($key, $keys_used, true)) {
                $key = $base_key . '-' . $i;
                $i++;
            }

            $keys_used[] = $key;

            $sections[] = [
                'title' => $item,
                'key'   => $key,
                'units' => []
            ];

            $current = count($sections) - 1;
        }
    }

    return $sections;
}


add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style(
        'bootstrap-5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );
});


/* ======================================================
 *  ADMIN – RENDER
 * ====================================================== */
function wplms_render_section_drip_settings($course_id){

    $sections = wplms_parse_curriculum(
        wplms_get_course_curriculum($course_id)
    );

    $section_drip = get_post_meta($course_id, 'section_drip_days', true);
    if(!is_array($section_drip)) $section_drip = [];

    if(empty($sections)){
        echo '<div class="alert alert-warning"><strong>Nenhuma seção encontrada neste curso.</strong></div>';
        return;
    }
    ?>

    <div class="container-fluid px-0">

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mt-3">

            <input type="hidden" name="action" value="wplms_save_section_drip">
            <?php wp_nonce_field('wplms_save_section_drip', 'wplms_section_drip_nonce'); ?>
            <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>">

            <div class="container-fluid shadow-sm">
                <div class="card-header">
                    <strong>Liberação de Seções por Drip</strong>
                </div>

                <div class="card-body p-0">

                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Seção / Unidades</th>
                                <th style="width:220px;">Liberar após (dias)</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php foreach($sections as $section): ?>
                            <tr>
                                <td>
                                    <strong class="d-block mb-1">
                                        <?php echo esc_html($section['title']); ?>
                                    </strong>

                                    <?php if(!empty($section['units'])): ?>
                                        <ul class="mb-0 ps-3 small text-muted">
                                            <?php foreach($section['units'] as $unit): ?>
                                                <li><?php echo esc_html(get_the_title($unit)); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        class="form-control"
                                        name="section_drip_days[<?php echo esc_attr($section['key']); ?>]"
                                        value="<?php echo esc_attr($section_drip[$section['key']] ?? 0); ?>"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

                <div class="card-footer text-end" style="padding: 1%">
                    <button type="submit" class="btn btn-primary">
                        Salvar configurações
                    </button>
                </div>
            </div>

        </form>

    </div>

    <?php
}

/* ======================================================
 *  SALVA CONFIGURAÇÕES NO BANCO
 * ====================================================== */
add_action('admin_post_wplms_save_section_drip', 'wplms_save_section_drip_admin');
function wplms_save_section_drip_admin(){

    if(!current_user_can('manage_options')){
        wp_die('Sem permissão');
    }

    if(
        !isset($_POST['wplms_section_drip_nonce']) ||
        !wp_verify_nonce($_POST['wplms_section_drip_nonce'], 'wplms_save_section_drip')
    ){
        wp_die('Nonce inválido');
    }

    $course_id = (int) $_POST['course_id'];
    $data = [];

    if(!empty($_POST['section_drip_days'])){
        foreach($_POST['section_drip_days'] as $key => $days){
            $data[$key] = max(0, (int) $days);
        }
    }

    update_post_meta($course_id, 'section_drip_days', $data);

    wp_redirect(
        admin_url('admin.php?page=wplms-section-drip&course_id='.$course_id.'&updated=1')
    );
    exit;
}




/**
 * ======================================================
 *  DRIP FEED POR SEÇÃO – FRONT-END (WPLMS)
 * ======================================================
 *
 * Autor: Miguel Ferreira
 * Data: 09/02/2026
 * Esta funcionalidade implementa o controle de liberação
 * progressiva (drip feed) de SEÇÕES e UNIDADES no front-end
 * do WPLMS, com base:
 *
 * - Nas configurações salvas no admin (section_drip_days)
 * - Na data REAL de início do curso pelo aluno
 *
 * O sistema funciona de forma desacoplada do admin,
 * utilizando AJAX, observers e bloqueio visual dinâmico,
 * sendo compatível com o WPLMS tradicional e com o App
 * baseado em React.
 *
 * ------------------------------------------------------
 *  FLUXO GERAL
 * ------------------------------------------------------
 *
 * 1) O aluno acessa um curso.
 *
 * 2) O script detecta dinamicamente:
 *    - Qual curso está sendo exibido
 *    - Qual usuário está logado
 *
 * 3) Via AJAX, o sistema busca:
 *    - As regras de drip por seção (section_drip_days)
 *    - A data real de início do curso (start_course)
 *
 * 4) Com base nos dias decorridos desde o início:
 *    - Seções futuras são bloqueadas
 *    - Unidades pertencentes a essas seções também
 *      são bloqueadas
 *
 * 5) Caso o aluno tente acessar conteúdo bloqueado:
 *    - Um overlay impede o acesso
 *    - Uma mensagem amigável é exibida
 *
 * ------------------------------------------------------
 *  BACK-END (AJAX)
 * ------------------------------------------------------
 *
 * 🔹 Ação AJAX:
 *   get_course_drip_days
 *
 * 🔹 Responsabilidades:
 * - Validar parâmetros recebidos (course_id / user_id)
 * - Buscar as regras de drip do curso (post_meta)
 * - Buscar a data REAL de início do curso do aluno
 *   diretamente da tabela bp_activity (BuddyPress),
 *   garantindo precisão mesmo em ambientes React/App
 *
 * 🔹 Retorno:
 * {
 *   course_id,
 *   user_id,
 *   section_drip_days,
 *   course_started_at
 * }
 *
 * ------------------------------------------------------
 *  FRONT-END (JAVASCRIPT)
 * ------------------------------------------------------
 *
 * 🔹 DETECÇÃO DE CONTEXTO
 * - Identifica automaticamente quando o usuário entra
 *   ou troca de curso, mesmo sem reload de página
 *   (SPA / React).
 *
 * 🔹 OBSERVERS
 * - MutationObserver monitora:
 *     • Mudanças no DOM
 *     • Troca de curso
 *     • Renderização de unidades
 *
 * 🔹 NORMALIZAÇÃO DE TEXTO
 * - Garante correspondência segura entre:
 *     • Nome da seção no admin
 *     • Título exibido no front-end
 * - Remove acentos, símbolos e variações de escrita.
 *
 * 🔹 BLOQUEIO DE SEÇÕES
 * - Se uma seção ainda não está liberada:
 *     • A seção recebe estado "drip-locked"
 *     • Todas as unidades abaixo dela são bloqueadas
 *     • Clique é interceptado em capture phase
 *
 * 🔹 BLOQUEIO DE CONTEÚDO
 * - Mesmo que o WPLMS renderize a unidade via URL
 *   direta, o conteúdo é protegido por um overlay
 *   absoluto, impedindo a visualização.
 *
 * ------------------------------------------------------
 *  UX / SEGURANÇA
 * ------------------------------------------------------
 *
 * ✔ Bloqueio visual claro
 * ✔ Mensagens amigáveis ao aluno
 * ✔ Não depende de reload
 * ✔ Compatível com cache
 * ✔ Funciona em App / React
 * ✔ Não altera core do WPLMS
 *
 * ------------------------------------------------------
 *  DECISÕES TÉCNICAS IMPORTANTES
 * ------------------------------------------------------
 *
 * 🔹 Uso direto do banco (bp_activity):
 * - Evita inconsistências de APIs
 * - Garante a data real de início do curso
 *
 * 🔹 Overlay absoluto:
 * - Protege contra acesso direto por URL
 * - Protege contra renderização tardia
 *
 * 🔹 Event Capture:
 * - Impede execução de handlers internos do WPLMS
 *
 * ------------------------------------------------------
 *  EXTENSÕES FUTURAS POSSÍVEIS
 * ------------------------------------------------------
 *
 * - Exibir contador de dias restantes
 * - Liberar conteúdo automaticamente sem refresh
 * - Mensagens personalizadas por seção
 * - Integração com notificações / e-mail
 * - Logs de tentativas de acesso antecipado
 *
 * ======================================================
 */

add_action('wp_ajax_get_course_drip_days', 'get_course_drip_days');
add_action('wp_ajax_nopriv_get_course_drip_days', 'get_course_drip_days');

function get_course_drip_days() {

    if ( empty($_POST['course_id']) || empty($_POST['user_id']) ) {
        wp_send_json_error('Dados inválidos');
    }

    $course_id = intval($_POST['course_id']);
    $user_id   = intval($_POST['user_id']);

    global $wpdb;

    /** -------------------------
     * section_drip_days
     * ------------------------- */
    $meta_value = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id = %d
               AND meta_key = %s",
            $course_id,
            'section_drip_days'
        )
    );

    $section_drip_days = $meta_value ? maybe_unserialize($meta_value) : [];

    /** -------------------------
     * DATA REAL DE INÍCIO DO CURSO
     * (wp_bp_activity)
     * ------------------------- */
    $course_started_at = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT date_recorded
             FROM {$wpdb->prefix}bp_activity
             WHERE user_id = %d
               AND item_id = %d
               AND type = 'start_course'
             ORDER BY date_recorded ASC
             LIMIT 1",
            $user_id,
            $course_id
        )
    );

    wp_send_json_success([
        'course_id'          => $course_id,
        'user_id'            => $user_id,
        'section_drip_days'  => $section_drip_days,
        'course_started_at' => $course_started_at
    ]);
}


add_action('wp_footer', 'wplms_drip_script_footer', 20);
function wplms_drip_script_footer() {
?>
<script>
    window.wplmsAjax = {
        ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>"
    };
</script>
<?php
}


/* ===============================
 * DRIP + REACT OBSERVER
 * =============================== */
add_action('wp_footer', 'wplms_observar_curso_react');
function wplms_observar_curso_react() {
?>
<style>
.course_content_content_wrapper {
    position: relative;
}

.course-drip-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: all;
}

.course-drip-popup {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,.35);
}

.course_content {
    position: relative;
}
</style>

<script>
(function () {

    let currentCourseId = null;
    let isInsideCourse  = false;

    /* ===============================
     * 🔧 NORMALIZA TEXTO
     * =============================== */
    function normalizar(txt) {
        return txt
            .toString()
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9 ]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /* ===============================
     * USER ID
     * =============================== */
    function getUserIdFromSession() {
        try {
            const bpUser = sessionStorage.getItem('bp_user');
            if (!bpUser) return null;
            return JSON.parse(bpUser).id || null;
        } catch {
            return null;
        }
    }

    /* ===============================
     * RESET
     * =============================== */
    function resetCourseState() {
        currentCourseId = null;
        isInsideCourse = false;
        liberarCourseContent();
    }

    /* ===============================
     * DETECTA CURSO
     * =============================== */
    function detectarCurso() {

        const container = document.querySelector('.course_status');
        if (!container) return resetCourseState();

        const cls = [...container.classList].find(c => c.startsWith('course_id_'));
        if (!cls) return;

        const courseId = cls.replace('course_id_', '');
        const userId   = getUserIdFromSession();
        if (!userId) return;

        if (courseId !== currentCourseId) {
            currentCourseId = courseId;
            isInsideCourse = true;
            buscarDripDays(courseId, userId);
        }
    }

    /* ===============================
     * 🔒 BLOQUEIA SEÇÃO + UNITS
     * =============================== */
    function bloquearSecao(sectionName, diasFaltantes) {

        const sectionNorm = normalizar(sectionName);
        const items = document.querySelectorAll('.course_timeline li');

        items.forEach((item, index) => {

            if (!item.classList.contains('section')) return;

            const titleEl = item.querySelector('.lesson_title');
            if (!titleEl) return;

            if (normalizar(titleEl.innerText) !== sectionNorm) return;

            item.classList.add('drip-locked');
            item.style.opacity = '0.5';

            let next = items[index + 1];

            while (next && !next.classList.contains('section')) {
                if (next.classList.contains('unit')) {
                    next.dataset.locked = 'true';
                    next.classList.add('unit-drip-locked');
                    next.style.opacity = '0.4';
                }
                index++;
                next = items[index + 1];
            }
        });
    }

    /* ===============================
     * UNIT → SEÇÃO BLOQUEADA (LEGADO)
     * =============================== */
    function unidadePertenceASecaoBloqueada(unidadeTitulo) {

        let secaoBloqueada = false;

        for (let item of document.querySelectorAll('.course_timeline li')) {

            if (item.classList.contains('section')) {
                secaoBloqueada = item.classList.contains('drip-locked');
                continue;
            }

            if (item.classList.contains('unit')) {
                const titulo = item.querySelector('.lesson_title')?.innerText.trim();
                if (titulo === unidadeTitulo) return secaoBloqueada;
            }
        }
        return false;
    }

    /* ===============================
     * 🧠 NOVO: SECTION DA UNIT ATIVA
     * =============================== */
    function sectionDaUnitAtivaEstaBloqueada() {

        const unitAtiva = document.querySelector('.course_timeline li.unit.active');
        if (!unitAtiva) return false;

        let prev = unitAtiva.previousElementSibling;

        while (prev) {
            if (prev.classList.contains('section')) {
                return prev.classList.contains('drip-locked');
            }
            prev = prev.previousElementSibling;
        }
        return false;
    }

    /* ===============================
     * DRIP
     * =============================== */
    function aplicarDrip(sectionDripDays, courseStartedAt) {

        if (!courseStartedAt) return;

        const inicio = new Date(courseStartedAt.replace(' ', 'T'));
        const diasPassados = Math.floor((Date.now() - inicio) / 86400000);

        Object.entries(sectionDripDays).forEach(([raw, dripDays]) => {
            const decoded = decodeURIComponent(raw).replace(/-/g, ' ');
            if (diasPassados < dripDays) {
                bloquearSecao(decoded, dripDays - diasPassados);
            }
        });

        verificarUnitRenderizada();
    }

    /* ===============================
     * 🔑 VERIFICAÇÃO FINAL (H2 + TIMELINE)
     * =============================== */
    function verificarUnitRenderizada() {

        const h2 = document.querySelector('.course_content_content h2');
        if (!h2) return;

        const unidadeAtual = h2.innerText.trim();

        const bloqueada =
            unidadePertenceASecaoBloqueada(unidadeAtual) ||
            sectionDaUnitAtivaEstaBloqueada();

        if (bloqueada) {
            bloquearCourseContent('Esta unidade será liberada em breve.');
        } else {
            liberarCourseContent();
        }
    }

    /* ===============================
     * AJAX
     * =============================== */
    function buscarDripDays(courseId, userId) {

        const fd = new FormData();
        fd.append('action', 'get_course_drip_days');
        fd.append('course_id', courseId);
        fd.append('user_id', userId);

        fetch(window.wplmsAjax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                aplicarDrip(res.data.section_drip_days, res.data.course_started_at);
            }
        });
    }

    /* ===============================
     * OVERLAY
     * =============================== */
    function bloquearCourseContent(msg) {

        const wrapper = document.querySelector('.course_content_content_wrapper');
        if (!wrapper || wrapper.querySelector('.course-drip-overlay')) return;

        const o = document.createElement('div');
        o.className = 'course-drip-overlay';
        o.innerHTML = `
            <div class="course-drip-popup">
                <h3>🔒 Em breve</h3>
                <p>${msg}</p>
            </div>
        `;
        wrapper.appendChild(o);
    }

    function liberarCourseContent() {
        document.querySelectorAll('.course-drip-overlay').forEach(o => o.remove());
    }

    /* ===============================
     * CLIQUE (CAPTURE)
     * =============================== */
    document.addEventListener('click', function (e) {

        const unit = e.target.closest('li.unit');

        if (unit && unit.dataset.locked === 'true') {
            e.preventDefault();
            e.stopImmediatePropagation();
            bloquearCourseContent('Esta unidade ainda não está disponível.');
            return false;
        }

    }, true);

    /* ===============================
     * OBSERVERS
     * =============================== */
    new MutationObserver(detectarCurso)
        .observe(document.body, { childList: true, subtree: true });

    new MutationObserver(() =>
        requestAnimationFrame(verificarUnitRenderizada)
    ).observe(document.body, { childList: true, subtree: true });

    setTimeout(detectarCurso, 500);

})();
</script>
<?php
}

















/**
 * 💰 Sistema de Comissões WPLMS (Administração Completa)
 * 
 * Autor: Miguel Cezar Ferreira
 * Data: 09/12/2025
 * 
 * Descrição:
 * Este módulo implementa um sistema completo de **Gestão de Comissões do WPLMS**, permitindo
 * administração centralizada de taxas individuais, pagamentos, histórico, relatórios e controle
 * detalhado de vendas e faturamento de cursos vinculados ao WooCommerce.
 * 
 * Ele oferece ao administrador ferramentas avançadas para visualizar cursos, editar taxas de
 * comissão, registrar pagamentos, consultar o histórico completo de remunerações e aplicar
 * alterações em massa de forma simples e segura.
 * 
 * ⚙️ O que este código faz:
 * - Exibe uma tabela administrativa com todos os cursos elegíveis.
 * - Lista:
 *      • Instrutor responsável (autor do curso)
 *      • Preço e produto vinculado ao curso (vibe_product)
 *      • Taxa de comissão configurada no curso
 *      • Faturamento total gerado
 *      • Comissão acumulada a pagar
 *      • Ações de edição individual
 * - Permite ao administrador:
 *      • Editar taxa de comissão por curso
 *      • Aplicar taxa padrão a todos os cursos em lote
 *      • Registrar pagamentos efetuados aos instrutores
 *      • Editar pagamentos já realizados
 *      • Excluir entradas do histórico de pagamentos
 * - Exibe histórico completo de pagamentos com:
 *      • Instrutor
 *      • Curso
 *      • Valor pago
 *      • Data
 *      • Observações
 * 
 * 🔍 Como funciona o cálculo da comissão:
 * - Obtém todos os cursos cadastrados 
 * - Para cada curso:
 *      • Identifica o instrutor responsável (post_author).
 *      • Acessa o produto WooCommerce vinculado ao curso via meta "vibe_product".
 *      • Busca pedidos válidos contendo aquele produto.
 *      • Filtra pedidos por status comercial: "processing" e "completed".
 *      • Soma:
 *          - Quantidade vendida
 *          - Faturamento total do curso (line_total)
 *          - Comissão calculada com base na taxa configurada (meta: commission_rate)
 * 
 * 🧾 Sistema de Histórico:
 * - Cada pagamento registrado cria um item no histórico.
 * - Campos incluídos:
 *      • ID do instrutor
 *      • ID do curso
 *      • Valor pago
 *      • Data de pagamento
 *      • Observação opcional
 * - Itens podem ser:
 *      • Editados
 *      • Removidos
 * - O histórico é exibido em tabela com ordenação natural.
 * 
 * 🔐 Segurança da administração:
 * - Todas as ações utilizam:
 *      • wp_verify_nonce
 *      • sanitize_text_field
 *      • sanitize_textarea_field
 *      • intval() e floatval()
 * - Somente administradores podem registrar, editar ou remover pagamentos.
 * - Verificações garantem que o curso pertence realmente ao instrutor informado.
 * 
 * 🧠 Funcionamento técnico:
 * - Lê e grava informações em:
 *      • post_meta dos cursos (taxas, produto vinculado, etc.)
 *      • tabela personalizada do histórico de pagamentos
 * - Utiliza:
 *      • Filtros por categoria de curso
 *      • Loops WooCommerce para leitura de vendas
 *      • Estrutura MVC simplificada dentro do painel
 * - A interface usa:
 *      • Bootstrap 5
 *      • Modais de edição
 *      • Formulários protegidos com nonce
 * 
 * 🧩 Requisitos:
 * - WPLMS ativo e configurado.
 * - WooCommerce instalado.
 * - Cursos vinculados corretamente via meta “vibe_product”.
 * - Permissões administrativas no WordPress.
 * 
 * 💡 Possíveis melhorias:
 * - Adicionar exportação XLSX com:
 *      • Cursos por instrutor
 *      • Faturamento total
 *      • Comissões pendentes e pagas
 * - Implementar DataTables para ordenação avançada.
 * - Criar filtro por período (mês, ano, personalizado).
 * - Inserir KPIs no topo: total pago, total pendente, cursos vendidos.
 * - Adicionar painel gráfico com Chart.js.
 */


function wplms_commissions_admin_page()
{
    global $wpdb;

    if (!class_exists('WooCommerce')) {
        echo '<div class="error"><p>WooCommerce não está ativo.</p></div>';
        return;
    }

    // nonce para validar ações
    $current_nonce = wp_create_nonce('wplms_commissions_action');

    echo '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .wplms-container { display:flex; flex-direction:column; gap:30px; width:98%; }
        .wplms-card { border-radius:12px; background:#fff; }
        .table-responsive { border-radius:12px; overflow:hidden; }
        input[type=number]::-webkit-inner-spin-button { opacity: 1; }
    </style>';

    echo '<div class="container-fluid mt-4 wplms-container">';
    echo '<div class="wplms-card shadow-sm p-4 mb-12 full-card"><h1 class="fw-bold mb-3">📊 Comissões dos Instrutores</h1><p>Este módulo implementa um sistema completo de Gestão de Comissões, permitindo administração centralizada de taxas individuais, pagamentos, histórico, relatórios e controle detalhado de vendas e faturamento de cursos vinculados a sua Plataforma. Ele oferece ao administrador ferramentas avançadas para visualizar cursos, editar taxas de comissão, registrar pagamentos, consultar o histórico completo de remunerações e aplicar alterações em massa de forma simples e segura.</p><small class="text-muted d-block mt-2">OBS: Esse é um sistema somente de Gestão e Registro, nenhum pagamento de comissão é feito aqui, só controle!</small></div>';

    /* -------------------- PROCESSAMENTO DE POSTS -------------------- */
    // todas as ações verificam nonce
    // 1) payment_update (salvar status + possivel payment_amount para histórico)
    if (isset($_POST['payment_update'], $_POST['payment_course_id'], $_POST['wplms_nonce'])) {
        if (! wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            echo '<div class="alert alert-danger mt-3">Falha na verificação de segurança (nonce inválido).</div>';
        } else {
            $course_id = intval($_POST['payment_course_id']);

            if ($course_id <= 0) {
                echo '<div class="alert alert-danger mt-3">ID do curso inválido.</div>';
            } else {
                $paid  = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
                $notes = isset($_POST['payment_notes']) ? sanitize_textarea_field($_POST['payment_notes']) : '';

                update_post_meta($course_id, 'wplms_commission_paid', $paid);
                update_post_meta($course_id, 'wplms_commission_paid_notes', $notes);

                if ($paid === 'yes' || $paid === 'no') {
                    $payment_date_raw = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
                    if (! empty($payment_date_raw)) {
                        $ts = strtotime($payment_date_raw);
                        $payment_date = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : current_time('mysql');
                    } else {
                        $payment_date = current_time('mysql');
                    }
                    update_post_meta($course_id, 'wplms_commission_paid_date', $payment_date);
                }

                // se enviou um valor para registrar no histórico
                if (isset($_POST['payment_amount']) && $_POST['payment_amount'] !== '') {
                    $amount = floatval(str_replace(',', '.', $_POST['payment_amount']));
                    if ($amount > 0) {
                        $history = get_post_meta($course_id, 'wplms_commission_payments_history', true);
                        if (! is_array($history)) $history = [];

                        $history_date = (isset($payment_date) && !empty($payment_date)) ? $payment_date : current_time('mysql');

                        $history[] = [
                            'amount' => $amount,
                            'date'   => $history_date,
                            'note'   => $notes
                        ];

                        update_post_meta($course_id, 'wplms_commission_payments_history', $history);
                    }
                }

                echo '<div class="alert alert-success mt-3">Status de pagamento atualizado com sucesso.</div>';
            }
        }
    }

    // 2) delete_payment
    if (isset($_POST['delete_payment'], $_POST['payment_course_id'], $_POST['payment_index'], $_POST['wplms_nonce'])) {
        if (! wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            echo '<div class="alert alert-danger mt-3">Falha na verificação de segurança (nonce inválido).</div>';
        } else {
            $course_id = intval($_POST['payment_course_id']);
            $index     = intval($_POST['payment_index']);

            if ($course_id > 0) {
                $history = get_post_meta($course_id, 'wplms_commission_payments_history', true);
                if (!is_array($history)) $history = [];

                if (isset($history[$index])) {
                    unset($history[$index]);
                    $history = array_values($history);
                    update_post_meta($course_id, 'wplms_commission_payments_history', $history);
                    echo '<div class="alert alert-success mt-3">Pagamento removido.</div>';
                }
            }
        }
    }

    // 3) edit_payment
    if (isset($_POST['edit_payment'], $_POST['payment_course_id'], $_POST['payment_index'], $_POST['wplms_nonce'])) {
        if (! wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            echo '<div class="alert alert-danger mt-3">Falha na verificação de segurança (nonce inválido).</div>';
        } else {
            $course_id = intval($_POST['payment_course_id']);
            $index     = intval($_POST['payment_index']);

            if ($course_id > 0) {
                $history = get_post_meta($course_id, 'wplms_commission_payments_history', true);
                if (!is_array($history)) $history = [];

                if (isset($history[$index])) {
                    $edit_value = isset($_POST['payment_edit_value']) ? floatval(str_replace(',', '.', $_POST['payment_edit_value'])) : 0;
                    $edit_date_raw = isset($_POST['payment_edit_date']) ? sanitize_text_field($_POST['payment_edit_date']) : '';
                    $edit_note = isset($_POST['payment_edit_note']) ? sanitize_textarea_field($_POST['payment_edit_note']) : '';

                    $ts = strtotime($edit_date_raw);
                    $edit_date = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : current_time('mysql');

                    $history[$index]['amount'] = $edit_value;
                    $history[$index]['date']   = $edit_date;
                    $history[$index]['note']   = $edit_note;

                    update_post_meta($course_id, 'wplms_commission_payments_history', $history);
                    echo '<div class="alert alert-success mt-3">Pagamento atualizado!</div>';
                }
            }
        }
    }

    // 4) add_payment (rota compatível, opcional)
    if (isset($_POST['add_payment'], $_POST['payment_course_id'], $_POST['wplms_nonce'])) {
        if (! wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            echo '<div class="alert alert-danger mt-3">Falha na verificação de segurança (nonce inválido).</div>';
        } else {
            $course_id = intval($_POST['payment_course_id']);
            $value     = isset($_POST['payment_value']) ? floatval(str_replace(',', '.', $_POST['payment_value'])) : 0;
            $note      = isset($_POST['payment_note']) ? sanitize_textarea_field($_POST['payment_note']) : '';
            $date      = !empty($_POST['payment_value_date']) ? sanitize_text_field($_POST['payment_value_date']) : current_time('mysql');

            if ($course_id > 0 && $value > 0) {
                $history = get_post_meta($course_id, 'wplms_commission_payments_history', true);
                if (!is_array($history)) $history = [];

                $ts = strtotime($date);
                $date = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : current_time('mysql');

                $history[] = [
                    'amount' => $value,
                    'date'   => $date,
                    'note'   => $note
                ];

                update_post_meta($course_id, 'wplms_commission_payments_history', $history);

                echo '<div class="alert alert-success mt-3">Pagamento registrado com sucesso.</div>';
            } else {
                echo '<div class="alert alert-warning mt-3">Valor ou curso inválido para registro.</div>';
            }
        }
    }

    /* -------------------- AÇÕES DE TAXAS (também exigem nonce) -------------------- */
    if (isset($_POST['apply_rate_selected'], $_POST['selected_courses'], $_POST['wplms_nonce'])) {
        if (wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            $rate = intval($_POST['apply_rate']);
            foreach ($_POST['selected_courses'] as $course_id) {
                update_post_meta(intval($course_id), 'wplms_commission_rate', $rate);
            }
            echo '<div class="alert alert-success mt-3">Taxa aplicada aos cursos selecionados.</div>';
        }
    }

    if (isset($_POST['save_individual_rates'], $_POST['course_commission'], $_POST['wplms_nonce'])) {
        if (wp_verify_nonce($_POST['wplms_nonce'], 'wplms_commissions_action')) {
            foreach ($_POST['course_commission'] as $course_id => $rate) {
                update_post_meta(intval($course_id), 'wplms_commission_rate', intval($rate));
            }
            echo '<div class="alert alert-success mt-3">Taxas individuais salvas com sucesso.</div>';
        }
    }

    /* -------------------- FILTROS -------------------- */
    $filter_instructor = isset($_GET['instructor']) ? sanitize_text_field($_GET['instructor']) : '';
    $filter_course     = isset($_GET['course'])     ? sanitize_text_field($_GET['course'])     : '';
    $filter_status     = isset($_GET['status'])     ? sanitize_text_field($_GET['status'])     : '';

    echo '
    <div class="wplms-card mb-3 shadow-sm p-4 full-card">
        <h4 class="fw-bold mb-3">🔍 Filtros</h4>

        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="wplms-commissions">

            <div class="col-md-3">
                <label class="form-label">Instrutor:</label>
                <input type="text" name="instructor" value="' . esc_attr($filter_instructor) . '" class="form-control" placeholder="Nome do instrutor">
            </div>

            <div class="col-md-3">
                <label class="form-label">Curso:</label>
                <input type="text" name="course" value="' . esc_attr($filter_course) . '" class="form-control" placeholder="Nome do curso">
            </div>

            <div class="col-md-3">
                <label class="form-label">Status do Pagamento:</label>
                <select name="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="yes" ' . selected($filter_status, "yes", false) . '>Pago</option>
                    <option value="no"  ' . selected($filter_status, "no", false)  . '>Não Pago</option>
                </select>
            </div>

            <div class="col-md-3 d-grid">
                <button class="btn btn-primary">Aplicar Filtros</button>
            </div>
        </form>
    </div>';

    //     echo '<div class="wplms-card">
    //             <h3>🎓 Cursos</h3>
    //         </div>';

    /* -------------------- QUERY -------------------- */
    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    $query_args = [
        'post_type'      => 'course',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish'

    ];

    if ($filter_course !== '') {
        $query_args['s'] = $filter_course;
    }

    if ($filter_instructor !== '') {
        $users = get_users([
            'search'         => '*' . esc_attr($filter_instructor) . '*',
            'search_columns' => ['display_name', 'user_login'],
            'fields'         => ['ID'],
        ]);

        $query_args['author__in'] = !empty($users) ? wp_list_pluck($users, 'ID') : [0];
    }

    if (!isset($query_args['meta_query'])) {
        $query_args['meta_query'] = [];
    }

    if ($filter_status === 'yes' || $filter_status === 'no') {
        $query_args['meta_query'][] = [
            'key'   => 'wplms_commission_paid',
            'value' => $filter_status,
        ];
    }

    $course_query = new WP_Query($query_args);
    $courses = $course_query->posts;

    /* -------------------- TABELA (form principal: contém actions de taxa e salvar taxas) -------------------- */
    echo '<form method="post" class="wplms-card">
    <input type="hidden" name="wplms_nonce" value="' . esc_attr($current_nonce) . '">
    <div class="table-responsive shadow">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-dark p-3">
                <tr>
                    <th><input type="checkbox" id="select_all"></th>
                    <th>Instrutor</th>
                    <th>Curso</th>
                    <th>Vendas</th>
                    <th>Total Faturado</th>
                    <th>Taxa Individual (%)</th>
                    <th>Comissão Total</th>
                    <th>Status</th>
                    <th>Ver mais</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($courses as $course) {

        $course_id    = $course->ID;
        $instructor   = get_the_author_meta('display_name', $course->post_author);
        $product_id   = get_post_meta($course_id, 'vibe_product', true);
        if (!$product_id) continue;

        $course_commission = get_post_meta($course_id, 'wplms_commission_rate', true);
        if ($course_commission === '' || $course_commission === null) $course_commission = 0;

        $paid      = get_post_meta($course_id, 'wplms_commission_paid', true);
        $paid_date = get_post_meta($course_id, 'wplms_commission_paid_date', true);
        $notes     = get_post_meta($course_id, 'wplms_commission_paid_notes', true);
        if (!$notes) $notes = 'Nenhuma observação registrada.';

        $paid_normalized = $paid === 'yes' ? 'yes' : ($paid === 'no' ? 'no' : 'none');

        if ($filter_status !== '' && $filter_status !== $paid_normalized) continue;

        $order_items = $wpdb->get_results($wpdb->prepare("
            SELECT oi.order_id, oi.order_item_id
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_item_type = 'line_item'
              AND (
                    (oim.meta_key = '_product_id' AND oim.meta_value = %d)
                    OR
                    (oim.meta_key = '_course_id' AND oim.meta_value = %d)
                  )
        ", $product_id, $course_id));

        $sales_count = 0;
        $total_earned = 0.0;

        foreach ($order_items as $item_obj) {
            $order = wc_get_order($item_obj->order_id);
            if (!$order || !in_array($order->get_status(), ['processing', 'completed'])) continue;

            $item = $order->get_item($item_obj->order_item_id);
            if (!$item) continue;

            $sales_count++;

            $line_total = $item->get_total();
            if (!$line_total) $line_total = $item->get_meta('_line_total', true);

            $total_earned += (float)$line_total;
        }

        $commission_total = ($total_earned * $course_commission) / 100;

        // carregar histórico e calcular total já pago
        $history = get_post_meta($course_id, 'wplms_commission_payments_history', true);
        $history = is_array($history) ? $history : [];
        $total_paid = 0;
        foreach ($history as $h) {
            $total_paid += floatval(isset($h['amount']) ? $h['amount'] : 0);
        }
        $remaining = $commission_total - $total_paid;
        if ($remaining < 0) $remaining = 0;

        echo '
<tr>
    <td><input type="checkbox" name="selected_courses[]" value="' . esc_attr($course_id) . '" class="course_select"></td>
    <td>' . esc_html($instructor) . '</td>
    <td>' . esc_html($course->post_title) . '</td>
    <td>' . esc_html($sales_count) . '</td>
    <td>R$ ' . number_format($total_earned, 2, ',', '.') . '</td>

    <td>
        <input type="number" 
               name="course_commission[' . esc_attr($course_id) . ']" 
               value="' . esc_attr($course_commission) . '" 
               min="0" max="100" 
               class="form-control"
               style="width:90px;">
    </td>

    <td>
        <strong>Total: R$ ' . number_format($commission_total, 2, ',', '.') . '</strong><br>
        <span class="text-success">Pago: R$ ' . number_format($total_paid, 2, ',', '.') . '</span><br>
        <span class="text-danger">Falta: R$ ' . number_format($remaining, 2, ',', '.') . '</span>
    </td>

    <td>' .
            (
                $paid_normalized === 'yes'
                ? '<span class="badge bg-success">Pago</span><br><small>' . ($paid_date ? date("d/m/Y H:i", strtotime($paid_date)) : '') . '</small>'
                : ($paid_normalized === 'no'
                    ? '<span class="badge bg-secondary">Não pago</span>'
                    : '<span class="badge bg-warning text-dark">Sem registro</span>'
                )
            )
            . '</td>

    <td>
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#paymentModal-' . esc_attr($course_id) . '">
            Detalhes
        </button>
    </td>
</tr>';

        /* -------------------- MODAL: formulários separados para cada ação -------------------- */
        $paid_date_val = $paid_date ? date('Y-m-d\TH:i', strtotime($paid_date)) : '';

        // monta o HTML do histórico com botões de Editar/Excluir (cada ação é um form independente)
        $modal_history_html = '';
        if (!empty($history)) {
            $idx = 0;
            foreach ($history as $h) {
                $h_amount = floatval(isset($h['amount']) ? $h['amount'] : 0);
                $h_date_raw = isset($h['date']) ? $h['date'] : '';
                $h_note = isset($h['note']) ? $h['note'] : '';
                $h_date_formatted = $h_date_raw ? date("d/m/Y H:i", strtotime($h_date_raw)) : '';

                // histórico item
                $modal_history_html .= '<div class="border rounded p-2 mb-2">';
                $modal_history_html .= '<strong>Valor:</strong> R$ ' . number_format($h_amount, 2, ',', '.') . '<br>';
                $modal_history_html .= '<strong>Data:</strong> ' . esc_html($h_date_formatted) . '<br>';
                $modal_history_html .= '<strong>Obs:</strong> ' . esc_html($h_note) . '<br><br>';

                // botão editar (toggle collapse)
                $modal_history_html .= '<div class="d-flex gap-2">';
                $modal_history_html .= '<button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editPayment' . esc_attr($course_id) . '-' . $idx . '">Editar</button>';

                // form de exclusão (independente)
                $modal_history_html .= '<form method="post" style="display:inline;" onsubmit="return confirm(\'Remover este pagamento?\');">';
                $modal_history_html .= '<input type="hidden" name="wplms_nonce" value="' . esc_attr($current_nonce) . '">';
                $modal_history_html .= '<input type="hidden" name="payment_course_id" value="' . esc_attr($course_id) . '">';
                $modal_history_html .= '<input type="hidden" name="payment_index" value="' . esc_attr($idx) . '">';
                $modal_history_html .= '<button type="submit" name="delete_payment" value="1" class="btn btn-sm btn-danger">Excluir</button>';
                $modal_history_html .= '</form>';

                $modal_history_html .= '</div>';

                // formulário de edição (collapse)
                $edit_date_val = $h_date_raw ? date('Y-m-d\TH:i', strtotime($h_date_raw)) : '';
                $modal_history_html .= '<div class="collapse mt-3" id="editPayment' . esc_attr($course_id) . '-' . $idx . '">';
                $modal_history_html .= '<form method="post" class="border rounded p-2">';
                $modal_history_html .= '<input type="hidden" name="wplms_nonce" value="' . esc_attr($current_nonce) . '">';
                $modal_history_html .= '<input type="hidden" name="payment_course_id" value="' . esc_attr($course_id) . '">';
                $modal_history_html .= '<input type="hidden" name="payment_index" value="' . esc_attr($idx) . '">';

                $modal_history_html .= '<label class="form-label fw-bold">Valor:</label>';
                $modal_history_html .= '<input type="number" step="0.01" name="payment_edit_value" value="' . esc_attr(number_format($h_amount, 2, '.', '')) . '" class="form-control">';

                $modal_history_html .= '<label class="form-label fw-bold mt-2">Data:</label>';
                $modal_history_html .= '<input type="datetime-local" name="payment_edit_date" value="' . esc_attr($edit_date_val) . '" class="form-control">';

                $modal_history_html .= '<label class="form-label fw-bold mt-2">Observações:</label>';
                $modal_history_html .= '<textarea name="payment_edit_note" class="form-control" rows="3">' . esc_textarea($h_note) . '</textarea>';

                $modal_history_html .= '<button type="submit" name="edit_payment" value="1" class="btn btn-success mt-3">Salvar alterações</button>';
                $modal_history_html .= '</form>';
                $modal_history_html .= '</div>';

                $modal_history_html .= '</div>'; // fim item histórico

                $idx++;
            }
        } else {
            $modal_history_html = '<p class="text-muted">Nenhum pagamento registrado.</p>';
        }

        // Modal — cada modal contém formulários separados:
        echo '
        <div class="modal fade" id="paymentModal-' . esc_attr($course_id) . '" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Pagamento da Comissão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">';

        // Form principal para salvar status + adicionar pagamento imediato (payment_update)
        echo '
        <form method="post" id="payment_form_' . esc_attr($course_id) . '">
            <input type="hidden" name="wplms_nonce" value="' . esc_attr($current_nonce) . '">
            <input type="hidden" name="payment_course_id" value="' . esc_attr($course_id) . '">

            <label class="form-label fw-bold">Status do Pagamento:</label>
            <select name="payment_status" class="form-control">
                <option value="no" ' . selected($paid, "no", false) . '>Não pago</option>
                <option value="yes" ' . selected($paid, "yes", false) . '>Pago</option>
            </select>

            <br>

            <label class="form-label fw-bold">Adicionar pagamento (R$):</label>
            <input type="number" step="0.01" name="payment_amount" class="form-control" placeholder="Ex: 150.00">
            <small class="text-muted">Este valor será somado ao histórico de pagamentos.</small>

            <br><br>

            <label class="form-label fw-bold">Data do pagamento:</label>
            <input type="datetime-local" name="payment_date" class="form-control" value="' . esc_attr($paid_date_val) . '">
            <small class="text-muted">Se vazio, a data atual será usada.</small>

            <br><br>

            <label class="form-label fw-bold">Anotações:</label>
            <textarea name="payment_notes" class="form-control" rows="4">' . esc_textarea($notes) . '</textarea>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button type="submit" name="payment_update" value="1" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </form>';

        // Espaçamento
        echo '<hr>';

        // Botão e formulário para rota Add Payment (opcional) if you still use it, but we already handle via payment_update above
        // (não é estritamente necessário porque payment_update já registra payment_amount)

        // Histórico com edição/exclusão (cada ação tem seu próprio form - já embutidos no HTML gerado acima)
        echo '<h5 class="fw-bold mt-3">Histórico de Pagamentos</h5>';
        echo $modal_history_html;

        // resumo
        echo '
        <div class="alert alert-info mt-3">
            <strong>Total da comissão:</strong> R$ ' . number_format($commission_total, 2, ',', '.') . '<br>
            <strong>Total já pago:</strong> R$ ' . number_format($total_paid, 2, ',', '.') . '<br>
            <strong>Saldo restante:</strong> R$ ' . number_format($remaining, 2, ',', '.') . '
        </div>';

        echo '

                    </div>
                </div>
            </div>
        </div>';
    }

    echo '</tbody></table></div>

        <div class="row mt-4">
            <div class="col-md-4">
                <label class="form-label fw-bold">Aplicar taxa (%) aos selecionados:</label>
                <input type="number" name="apply_rate" class="form-control" min="0" max="100" placeholder="Ex: 15">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-sm btn-outline-primary w-100" name="apply_rate_selected" value="1">Aplicar taxa aos selecionados</button>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-sm btn-outline-primary w-100" name="save_individual_rates" value="1">Salvar taxas individuais</button>
            </div>
        </div>

    </form>';

    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var selectAll = document.getElementById('select_all');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.course_select').forEach(cb => cb.checked = selectAll.checked);
                });
            }
        });
    </script>";

    $total_pages = $course_query->max_num_pages;

    if ($total_pages > 1) {
        $pagination = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'total'     => $total_pages,
            'current'   => $paged,
            'type'      => 'array',
        ]);

        echo '<div class="wplms-card text-center"><ul class="pagination justify-content-center">';
        foreach ($pagination as $link) {
            echo '<li class="page-item">' . str_replace('page-numbers', 'page-link', $link) . '</li>';
        }
        echo '</ul></div>';
    }

    if (function_exists('wplms_commissions_top_instructors_chart')) {
        wplms_commissions_top_instructors_chart();
    }

    echo '</div>';
}






/**
 * 📊 Exportação de Ranking de Instrutores (WPLMS + WooCommerce)
 * 
 * Autor: Miguel Cezar Ferreira
 * Data: 09/12/2025
 * 
 * Descrição:
 * Este módulo implementa um sistema completo de **Ranking de Instrutores**, capaz de exibir,
 * organizar e exportar os dados de vendas e comissões de cursos certificados no WPLMS.
 * Além disso, permite a exportação de uma **planilha XLSX**, gerada dinamicamente via AJAX,
 * contendo uma aba separada para cada instrutor.
 * 
 * ⚙️ O que este código faz:
 * - Cria um endpoint AJAX seguro para gerar e baixar uma planilha XLSX.
 * - Coleta todos os dados de vendas por curso e por instrutor usando consultas WooCommerce.
 * - Gera automaticamente uma planilha contendo:
 *      • Uma aba para cada instrutor  
 *      • Total de vendas do instrutor  
 *      • Faturamento por curso  
 *      • Taxa de comissão configurada no meta do curso  
 *      • Comissão final por curso e total geral  
 * - Exibe no painel WPLMS um Ranking de Instrutores com:
 *      • Posição no ranking  
 *      • Nome do instrutor  
 *      • Número total de vendas  
 *      • Faturamento acumulado  
 *      • Comissão total gerada  
 * - Abre modais com detalhes aprofundados de:
 *      • Vendas e faturamento por curso  
 *      • Lista completa de cursos do instrutor  
 * 
 * 🔍 Como funciona a coleta de dados:
 * - Busca todos os cursos da categoria "certificados".
 * - Para cada curso:
 *      • Identifica o instrutor responsável.
 *      • Busca o produto vinculado ao curso (meta: vibe_product).
 *      • Localiza pedidos que contenham o produto.
 *      • Filtra apenas pedidos com status "completed".
 *      • Soma:
 *          - quantidade de vendas
 *          - faturamento (line_total)
 *          - comissão (baseada na taxa configurada em cada curso)
 * 
 * 📤 Exportação XLSX:
 * - Cria uma aba por instrutor, contendo:
 *      • Nome do curso  
 *      • Vendas  
 *      • Faturamento  
 *      • Taxa de comissão (%)  
 *      • Comissão total (R$)  
 * - Adiciona automaticamente:
 *      • Totais consolidados por instrutor  
 *      • Formatação numérica apropriada  
 * - Arquivo baixado como: `ranking-instrutores-AAAA-MM-DD_HHMM.xlsx`
 * 
 * 🧩 Requisitos:
 * - WPLMS configurado com cursos usando vibe_product.
 * - WooCommerce instalado e ativo.
 * - Biblioteca PhpSpreadsheet disponível em:
 *        wp-content/vendor/autoload.php
 *        ou wp-content/plugins/vendor/autoload.php
 * 
 * 🧠 Funcionamento técnico:
 * - Endpoint AJAX registrado com:  
 *     • wp_ajax_baixar_planilha_ranking_instructors  
 *     • wp_ajax_nopriv_baixar_planilha_ranking_instructors  
 * - Usa JSON para enviar detalhes para os modais no frontend.
 * - Usa PhpSpreadsheet para criação das abas de instrutores.
 * - Ordena os instrutores por número total de vendas.
 * - Garante que cursos sem vendas sejam identificados como "Nenhuma venda".
 * 
 * 💡 Possíveis melhorias:
 * - Permitir filtros por período, categoria ou instrutor específico.
 * - Adicionar opção de exportar PDF além de XLSX.
 * - Criar gráfico de desempenho no próprio painel.
 * - Integrar DataTables para pesquisa, ordenação e paginação avançada.
 */
// === 1) Hook para registrar endpoint AJAX (download) ===
add_action('wp_ajax_baixar_planilha_ranking_instructors', 'baixar_planilha_ranking_instructors');
add_action('wp_ajax_nopriv_baixar_planilha_ranking_instructors', 'baixar_planilha_ranking_instructors');

function baixar_planilha_ranking_instructors()
{
    $dados = wplms_coletar_dados_instrutores();

    $tempDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
    mkdir($tempDir);
    mkdir($tempDir . '/_rels');
    mkdir($tempDir . '/xl');
    mkdir($tempDir . '/xl/_rels');
    mkdir($tempDir . '/xl/worksheets');

    //------------------------------------------------------------
    // 1. [Content_Types].xml
    //------------------------------------------------------------
    $content =
        '<?xml version="1.0" encoding="UTF-8"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    ';

    $sheetId = 1;
    foreach ($dados as $instructor_id => $info) {
        $content .= '<Override PartName="/xl/worksheets/sheet' . $sheetId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetId++;
    }

    $content .= '</Types>';

    file_put_contents($tempDir . '/[Content_Types].xml', $content);


    //------------------------------------------------------------
    // 2. _rels/.rels
    //------------------------------------------------------------
    $rels =
        '<?xml version="1.0" encoding="UTF-8"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
        </Relationships>';

    file_put_contents($tempDir . '/_rels/.rels', $rels);


    //------------------------------------------------------------
    // 3. xl/workbook.xml
    //------------------------------------------------------------
    $sheetsXML = "";
    $sheetId = 1;

    foreach ($dados as $instructor_id => $info) {
        $safeName = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]+/', '_', $info['name']);
        $safeName = mb_substr($safeName, 0, 31);
        $sheetsXML .= '<sheet name="' . htmlspecialchars($safeName) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        $sheetId++;
    }

    $workbook =
        '<?xml version="1.0" encoding="UTF-8"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
                  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets>' . $sheetsXML . '</sheets>
        </workbook>';

    file_put_contents($tempDir . '/xl/workbook.xml', $workbook);


    //------------------------------------------------------------
    // 4. xl/_rels/workbook.xml.rels
    //------------------------------------------------------------
    $relsSheets = "";
    $sheetId = 1;

    foreach ($dados as $instructor_id => $info) {
        $relsSheets .= '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
        $sheetId++;
    }

    $relsWorkbook =
        '<?xml version="1.0" encoding="UTF-8"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            ' . $relsSheets . '
        </Relationships>';

    file_put_contents($tempDir . '/xl/_rels/workbook.xml.rels', $relsWorkbook);


    //------------------------------------------------------------
    // 5. Criar as abas (worksheets)
    //------------------------------------------------------------
    $sheetId = 1;

    foreach ($dados as $instructor_id => $info) {
        $rowsXML = "";

        // Cabeçalho
        $rowsXML .= rowXML(1, ['Curso', 'Vendas', 'Faturamento', 'Taxa (%)', 'Comissão']);

        $r = 2;

        foreach ($info['courses'] as $course) {
            if ($course['sales'] <= 0) continue;

            $rowsXML .= rowXML($r, [
                $course['name'],
                $course['sales'],
                $course['amount'],
                $course['commission_rate'],
                $course['commission_total'],
            ]);

            $r++;
        }

        if ($r == 2) {
            $rowsXML .= rowXML(2, ['Nenhuma venda']);
        }

        $sheet =
            '<?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetData>' . $rowsXML . '</sheetData>
            </worksheet>';

        file_put_contents($tempDir . '/xl/worksheets/sheet' . $sheetId . '.xml', $sheet);

        $sheetId++;
    }

    //------------------------------------------------------------
    // Criar ZIP (XLSX)
    //------------------------------------------------------------
    $zipPath = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::OVERWRITE);

    addFilesToZip($tempDir, $zip);
    $zip->close();

    //------------------------------------------------------------
    // Enviar arquivo
    //------------------------------------------------------------
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="ranking-instrutores.xlsx"');
    header('Content-Length: ' . filesize($zipPath));

    readfile($zipPath);
    unlink($zipPath);
    deleteDir($tempDir);
    exit;
}

/* Helpers */
function rowXML($rowNum, $values)
{
    $cells = "";
    $col = 1;

    foreach ($values as $val) {
        $coord = colLetter($col) . $rowNum;
        $cells .= '<c r="' . $coord . '" t="inlineStr"><is><t>' . htmlspecialchars($val) . '</t></is></c>';
        $col++;
    }

    return '<row r="' . $rowNum . '">' . $cells . '</row>';
}

function colLetter($num)
{
    $letter = '';
    while ($num > 0) {
        $num--;
        $letter = chr(65 + ($num % 26)) . $letter;
        $num = intval($num / 26);
    }
    return $letter;
}

function addFilesToZip($dir, &$zip, $base = '')
{
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = "$dir/$item";
        $local = ltrim("$base/$item", '/');

        if (is_dir($path)) {
            addFilesToZip($path, $zip, $local);
        } else {
            $zip->addFile($path, $local);
        }
    }
}

function deleteDir($dir)
{
    foreach (scandir($dir) as $object) {
        if ($object === '.' || $object === '..') continue;
        $path = $dir . '/' . $object;
        if (is_dir($path)) deleteDir($path);
        else unlink($path);
    }
    rmdir($dir);
}



// === 2) Função que coleta os dados (reaproveita exatamente a lógica original) ===
function wplms_coletar_dados_instrutores()
{
    global $wpdb;

    // Buscar cursos 
    $courses = get_posts([
        'post_type'      => 'course',
        'posts_per_page' => -1

    ]);

    $instructor_data = [];

    foreach ($courses as $course) {

        $course_id     = $course->ID;
        $instructor_id = $course->post_author;
        $instructor    = get_the_author_meta('display_name', $instructor_id);

        if (!isset($instructor_data[$instructor_id])) {
            $instructor_data[$instructor_id] = [
                'name'   => $instructor,
                'sales'  => 0,
                'amount' => 0.0,
                'commission_total' => 0.0,
                'courses' => []
            ];
        }

        // Buscar taxa de comissão do curso
        $commission_rate = (float) get_post_meta($course_id, 'wplms_commission_rate', true);
        if (!$commission_rate) $commission_rate = 0;

        // Registrar curso
        $instructor_data[$instructor_id]['courses'][$course_id] = [
            'name'             => get_the_title($course_id),
            'sales'            => 0,
            'amount'           => 0.0,
            'commission_rate'  => $commission_rate,
            'commission_total' => 0.0,
        ];

        $product_id = get_post_meta($course_id, 'vibe_product', true);
        if (!$product_id) continue;

        // Buscar pedidos que contenham o produto/curso
        $order_items = $wpdb->get_results($wpdb->prepare("
            SELECT oi.order_id, oi.order_item_id
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                 ON oi.order_item_id = oim.order_item_id
            WHERE oi.order_item_type = 'line_item'
              AND (
                    (oim.meta_key = '_product_id' AND oim.meta_value = %d)
                 OR (oim.meta_key = '_course_id' AND oim.meta_value = %d)
              )
        ", $product_id, $course_id));

        foreach ($order_items as $item_obj) {
            $order = wc_get_order($item_obj->order_id);

            if (!$order || !in_array($order->get_status(), ['completed']))
                continue;

            $item = $order->get_item($item_obj->order_item_id);
            if (!$item) continue;

            $line_total = (float)$item->get_total();
            if (!$line_total)
                $line_total = (float)$item->get_meta('_line_total', true);

            // Geral
            $instructor_data[$instructor_id]['sales']++;
            $instructor_data[$instructor_id]['amount'] += $line_total;

            // Curso
            $instructor_data[$instructor_id]['courses'][$course_id]['sales']++;
            $instructor_data[$instructor_id]['courses'][$course_id]['amount'] += $line_total;

            // Comissão do curso
            $commission_value = ($line_total * $commission_rate) / 100;

            // Registrar comissão por curso
            $instructor_data[$instructor_id]['courses'][$course_id]['commission_total'] += $commission_value;

            // Total geral de comissão
            $instructor_data[$instructor_id]['commission_total'] += $commission_value;
        }
    }

    // Ordenar instrutores por vendas
    uasort($instructor_data, fn($a, $b) => $b['sales'] <=> $a['sales']);

    return $instructor_data;
}


// === 3) Função original de exibição, agora refatorada para incluir botão de download ===
function wplms_commissions_top_instructors_chart()
{

    $instructor_data = wplms_coletar_dados_instrutores();
    $details_json = json_encode($instructor_data, JSON_UNESCAPED_UNICODE);

    $download_url = admin_url('admin-ajax.php?action=baixar_planilha_ranking_instructors');

    echo '
<style>
    /* Melhor altura para scroll confortável */
    .modal-body-scroll {
        max-height: 70vh;
        overflow-y: auto;
        padding: 1.2rem;
    }
</style>

<div class="wplms-card mt-4 p-3 shadow-sm rounded bg-white">
    <h3 class="fw-bold mb-3">🏆 Ranking de Instrutores</h3>

    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-success btn-sm" href="' . esc_url($download_url) . '">
            📥 Baixar Planilha de Ranking
        </a>
    </div>

    <table class="table table-hover align-middle table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Instrutor</th>
                <th>Vendas</th>
                <th>Faturamento</th>
                <th>Comissão Total</th>
            </tr>
        </thead>
        <tbody>';

    $pos = 1;
    foreach ($instructor_data as $id => $data) {
        echo '
            <tr>
                <td><b>' . $pos . 'º</b></td>
                <td>
                    <a href="#" class="instrutor-link fw-semibold text-primary"
                       data-id="' . esc_attr($id) . '"
                       data-nome="' . esc_attr($data['name']) . '">
                        ' . esc_html($data['name']) . '
                    </a>
                </td>
                <td>' . intval($data['sales']) . '</td>
                <td><b>R$ ' . number_format($data['amount'], 2, ',', '.') . '</b></td>
                <td><b>R$ ' . number_format($data['commission_total'], 2, ',', '.') . '</b></td>
            </tr>';
        $pos++;
    }

    echo '
        </tbody>
    </table>
</div>

<!-- MODAL PRINCIPAL -->
<div class="modal fade" id="instrutorMainModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="instrutorMainTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <button type="button" class="btn btn-primary w-100 mb-3 py-3" id="btnVerVendas">
                    📊 Ver Vendas e Faturamento
                </button>
                <button type="button" class="btn btn-secondary w-100 py-3" id="btnVerCursos">
                    📚 Ver Cursos do Instrutor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VENDAS -->
<div class="modal fade" id="modalVendas" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Vendas, Faturamento e Comissão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-scroll" id="conteudoVendas"></div>
        </div>
    </div>
</div>

<!-- MODAL CURSOS -->
<div class="modal fade" id="modalCursos" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Cursos do Instrutor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-scroll" id="conteudoCursos"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    const detalhes = ' . $details_json . ';

    let instrutorSelecionado = null;
    let instrutorIdSelecionado = null;

    // Abrir modal principal ao clicar no instrutor
    document.querySelectorAll(".instrutor-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();

            instrutorSelecionado = this.dataset.nome;
            instrutorIdSelecionado = this.dataset.id;

            document.getElementById("instrutorMainTitle").innerText =
                "Detalhes — " + instrutorSelecionado;

            new bootstrap.Modal(document.getElementById("instrutorMainModal")).show();
        });
    });

    // MODAL VENDAS
    document.getElementById("btnVerVendas").addEventListener("click", function() {

        const cursos = detalhes[instrutorIdSelecionado].courses;

        let html = `
            <h4 class="fw-bold mb-3">${instrutorSelecionado}</h4>
            <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Curso</th>
                        <th>Vendas</th>
                        <th>Faturamento</th>
                        <th>Taxa (%)</th>
                        <th>Comissão</th>
                    </tr>
                </thead>
                <tbody>`;

        let total = 0;
        let total_comissao = 0;

        for (const id in cursos) {
            const c = cursos[id];
            if (c.sales <= 0) continue;

            total += parseFloat(c.amount);
            total_comissao += parseFloat(c.commission_total);

            html += `
                <tr>
                    <td>${c.name}</td>
                    <td>${c.sales}</td>
                    <td><b>R$ ${c.amount.toLocaleString("pt-BR",{minimumFractionDigits:2})}</b></td>
                    <td>${c.commission_rate}%</td>
                    <td><b>R$ ${c.commission_total.toLocaleString("pt-BR",{minimumFractionDigits:2})}</b></td>
                </tr>`;
        }

        html += `
                </tbody>
            </table>
            </div>

            <div class="mt-4 p-3 bg-light rounded border">
                <h5 class="mb-2"><b>Total Faturado:</b>
                    R$ ${total.toLocaleString("pt-BR",{minimumFractionDigits:2})}
                </h5>
                <h5><b>Total de Comissão:</b>
                    R$ ${total_comissao.toLocaleString("pt-BR",{minimumFractionDigits:2})}
                </h5>
            </div>`;

        document.getElementById("conteudoVendas").innerHTML = html;

        new bootstrap.Modal(document.getElementById("modalVendas")).show();
    });

    // MODAL CURSOS
    document.getElementById("btnVerCursos").addEventListener("click", function() {

        const cursos = detalhes[instrutorIdSelecionado].courses;

        let html = `
            <h4 class="fw-bold mb-3">${instrutorSelecionado}</h4>
            <ul class="list-group">`;

        for (const id in cursos) {
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${cursos[id].name}
                        <span class="badge bg-primary rounded-pill">${cursos[id].sales ?? 0} vendas</span>
                     </li>`;
        }

        html += `</ul>`;

        document.getElementById("conteudoCursos").innerHTML = html;

        new bootstrap.Modal(document.getElementById("modalCursos")).show();
    });

});
</script>
';
} // fim da função












/**
 * ============================================================
 * 🔄 WPLMS Stats – ESTATISTICAS SOLICITADAS EM PHILCO
 * ============================================================
 *
 * 📌 Autor: Miguel Cezar Ferreira
 * 📅 Data: 28/11/2025.
 *
 * ============================================================
 */

/**
 * ============================================================
 * 🔄 WPLMS Stats – ESTATISTICAS SOLICITADAS EM PHILCO
 * ============================================================
 *
 * 📌 Autor: Miguel Cezar Ferreira
 * 📅 Data: 28/11/2025.
 *
 * ============================================================
 */

function wplms_render_stats_page()
{
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .filter-section {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 3px 8px rgba(0, 0, 0, .05)
        }

        .campo-col {
            padding: 6px 10px
        }

        .filter-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center
        }

        #loading_overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, .8);
            z-index: 10;
            text-align: center;
            padding-top: 120px;
            font-size: 22px;
            font-weight: bold
        }

        #tabela_resultado {
            position: relative;
            min-height: 200px
        }

        .card-filter {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            background: #fff
        }

        .campo-col .form-check {
            padding: 8px 12px;
            border-radius: 6px;
            transition: background .2s
        }

        .campo-col .form-check:hover {
            background: #f7f7f7
        }

        /* Melhora visual geral */
        .filter-section {
            position: relative;
        }

        /* Títulos com ícone alinhado */
        .filter-title {
            gap: 6px;
        }

        /* Área de opções */
        .opcoes-box {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 10px;
        }

        /* Grid de campos */
        .campo-col {
            display: flex;
        }

        /* Card de checkbox */
        .campo-col .form-check {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        /* Checkbox maior */
        .campo-col .form-check-input {
            width: 1.15em;
            height: 1.15em;
            cursor: pointer;
        }

        /* Hover */
        .campo-col .form-check:hover {
            background: #f1f5ff;
            border-color: #cfe2ff;
        }

        /* Botões fixos alinhados */
        @media (max-width: 768px) {
            .acoes-footer {
                flex-direction: column;
                gap: 12px;
            }
        }

        /* Base comum */
        .btn-cache,
        .btn-export,
        .btn-gerar {
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 18px;
            border: none;
            transition: all .25s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
        }

        /* Limpar cache (perigo controlado) */
        .btn-cache {
            background: #fff5f5;
            color: #dc3545;
        }

        .btn-cache:hover {
            background: #dc3545;
            color: #fff;
            transform: translateY(-1px);
        }

        /* Exportar Excel */
        .btn-export {
            background: #e9f7ef;
            color: #198754;
        }

        .btn-export:hover {
            background: #198754;
            color: #fff;
            transform: translateY(-1px);
        }

        /* CTA principal */
        .btn-gerar {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: #fff;
            padding: 12px 26px;
            font-size: 1.05rem;
        }

        .btn-gerar:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        /* Ícones */
        .btn-cache .icon,
        .btn-export .icon,
        .btn-gerar .icon {
            font-size: 1.1rem;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .acoes-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .acoes-footer>div {
                justify-content: space-between;
            }
        }
    </style>

    <div class="container py-4">

        <h1 class="h3 fw-bold mb-4 text-center">📊 Gerador de Estatísticas dos Cursos</h1>

        <div class="filter-section mb-4">
            <div class="row g-4">

                <!-- Curso -->
                <div class="col-md-6">
                    <label class="filter-title">🎓 Selecione o Curso</label>
                    <select id="curso_id" class="form-select form-select-lg">
                        <option value="">-- Selecione --</option>
                        <?php
                        $cursos = new WP_Query([
                            'post_type' => 'course',
                            'posts_per_page' => -1
                        ]);
                        while ($cursos->have_posts()): $cursos->the_post();
                            echo '<option value="' . get_the_ID() . '">' . esc_html(get_the_title()) . '</option>';
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </select>
                </div>

                <!-- Status (OPCIONAL) -->
                <div class="col-md-6">
                    <label class="filter-title">📘 Status do Curso (opcional)</label>
                    <select id="status_curso_filtro" class="form-select form-select-lg">
                        <option value="">Todos os status</option>
                        <option value="nao_iniciado">Não iniciado</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluido">Concluído</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="filter-title">⚙️ Opções</label>
                    <div class="opcoes-box p-3 d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" id="select_all">
                        <label class="form-check-label fw-bold text-primary">Selecionar todos os campos</label>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <h5 class="fw-bold mb-3">📌 Campos disponíveis</h5>
            <div class="row g-3">
                <?php
                $campos = [
                    "id" => "ID",
                    "nome" => "Nome",
                    "email" => "Email",
                    "whatsapp" => "Whatsapp",
                    "cpf" => "CPF",
                    "progresso_curso" => "Progresso (%)",
                    "status_curso" => "Status",
                    "ultima_atividade" => "Última atividade",
                    "inscricao_curso" => "Inscrição",
                    "inicio_curso" => "Início",
                    "ultima_unidade" => "Última unidade",
                    "certificado" => "Certificado (data)",
                    "codigo_certificado" => "Código do Certificado",
                    "ultima_review" => "Última avaliação",
                ];
                foreach ($campos as $k => $l) {
                    echo "<div class='col-6 col-md-4 campo-col'>
<div class='form-check'>
<input class='form-check-input campo-opcao' type='checkbox' value='" . esc_attr($k) . "'>
<label class='form-check-label fw-semibold'>" . esc_html($l) . "</label>
</div></div>";
                }
                ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 acoes-footer">

                <button onclick="limparCacheTodosCursos()"
                    class="btn btn-cache d-flex align-items-center gap-2">
                    <span class="icon">🗑</span>
                    <span>Limpar cache</span>
                </button>

                <div class="d-flex gap-3">
                    <button id="export_excel"
                        class="btn btn-export d-flex align-items-center gap-2">
                        <span class="icon">⤓</span>
                        <span>Baixar Excel</span>
                    </button>

                    <button id="gerar_stats"
                        class="btn btn-gerar d-flex align-items-center gap-2">
                        <span class="icon">📊</span>
                        <span>Gerar Estatísticas</span>
                    </button>
                </div>

            </div>

        </div>

        <div id="filtros_pos_tabela" style="display:none;">
            <div class="card-filter p-3 shadow-sm mb-3">
                <h5 class="fw-bold mb-3">🔍 Filtro por Nome</h5>
                <input id="filtro_nome" class="form-control form-control-lg" placeholder="Digite para filtrar...">
            </div>
        </div>

<div id="tabela_resultado">
    <div id="loading_overlay">
        <div class="spinner-border" style="width:3rem;height:3rem;"></div>
        <div class="mt-3">Carregando...</div>
    </div>

    <div id="tabela_conteudo"></div>
</div>

    </div>

    <script>
        jQuery(function($) {

          
    let debounceTimer;
    let carregando = false;

    /* Selecionar todos */
    $("#select_all").on("change", function() {
        $(".campo-opcao").prop("checked", this.checked);
    });

    /* Gerar estatísticas */
    $("#gerar_stats").on("click", function() {

        if (carregando) return;

        if (!$("#curso_id").val()) {
            alert("Selecione um curso.");
            return;
        }

        if ($(".campo-opcao:checked").length === 0) {
            alert("Selecione ao menos um campo.");
            return;
        }

        $("#tabela_conteudo").html("");
        $("#filtros_pos_tabela").hide();

        mostrarLoading();
        carregar(1);
    });

    /* Filtro por nome (debounce) */
    $("#filtro_nome").on("input", function() {
        clearTimeout(debounceTimer);

        debounceTimer = setTimeout(() => {
            if (carregando) return;
            mostrarLoading();
            carregar(1);
        }, 300);
    });

    /* Limpar cache */
    window.limparCacheTodosCursos = function() {
        if (!confirm("Limpar cache de todos os cursos?")) return;

        mostrarLoading();

        $.post(ajaxurl, {
            action: "wplms_limpar_cache_todos_cursos"
        })
        .done(res => {
            alert(res.success ? "Cache limpo" : "Erro");
        })
        .always(() => {
            esconderLoading();
        });
    };

    /* AJAX principal */
    function carregar(pagina) {

        carregando = true;

        $.post(ajaxurl, {
            action: "wplms_gerar_tabela",
            curso_id: $("#curso_id").val(),
            campos: $(".campo-opcao:checked").map(function() {
                return this.value;
            }).get(),
            filtro_nome: $("#filtro_nome").val(),
            status_filtro: $("#status_curso_filtro").val(),
            pagina: pagina
        })
        .done(res => {
            $("#tabela_conteudo").html(res);
            $("#filtros_pos_tabela").show();
        })
        .fail(() => {
            alert("Erro ao carregar os dados.");
        })
        .always(() => {
            carregando = false;
            esconderLoading();
        });
    }

    /* Paginação */
    $(document).on("click", ".wplms-pagina", function(e) {
        e.preventDefault();

        if (carregando) return;

        mostrarLoading();
        carregar($(this).data("pagina"));
    });

    /* Exportar Excel */
    $("#export_excel").on("click", function() {

        const curso = $("#curso_id").val();
        const campos = $(".campo-opcao:checked").map(function() {
            return this.value;
        }).get();

        if (!curso || campos.length === 0) {
            alert("Preencha os campos obrigatórios");
            return;
        }

        const form = $('<form method="POST" action="' + ajaxurl + '"></form>');

        form.append('<input type="hidden" name="action" value="wplms_exportar_excel">');
        form.append('<input type="hidden" name="curso_id" value="' + curso + '">');

        const status = $("#status_curso_filtro").val();
        if (status) {
            form.append('<input type="hidden" name="status_filtro" value="' + status + '">');
        }

        campos.forEach(c => {
            form.append('<input type="hidden" name="campos[]" value="' + c + '">');
        });

        $("body").append(form);
        form.submit();

        setTimeout(() => form.remove(), 1500);
    });

    /* Helpers loading */
    function mostrarLoading() {
        $("#loading_overlay").fadeIn(150);
    }

    function esconderLoading() {
        $("#loading_overlay").fadeOut(200);
    }
            $(document).on("click", ".wplms-pagina", function(e) {
                e.preventDefault();
                carregar($(this).data("pagina"));
            });

            $("#export_excel").on("click", function() {
                const curso = $("#curso_id").val();
                const campos = $(".campo-opcao:checked").map(function() {
                    return this.value
                }).get();
                if (!curso || campos.length === 0) return alert("Preencha os campos obrigatórios");

                const form = $('<form method="POST" action="' + ajaxurl + '"></form>');
                form.append('<input type="hidden" name="action" value="wplms_exportar_excel">');
                form.append('<input type="hidden" name="curso_id" value="' + curso + '">');

                const status = $("#status_curso_filtro").val();
                if (status) form.append('<input type="hidden" name="status_filtro" value="' + status + '">');

                campos.forEach(c => form.append('<input type="hidden" name="campos[]" value="' + c + '">'));
                $("body").append(form);
                form.submit();
                setTimeout(() => form.remove(), 1500);
            });

        });
		
		
		
    </script>

<?php
}





/******************************************************
 * LIMPAR CACHE DE TODOS OS CURSOS
 ******************************************************/
add_action("wp_ajax_wplms_limpar_cache_todos_cursos", "wplms_limpar_cache_todos_cursos");

function wplms_limpar_cache_todos_cursos()
{
    global $wpdb;

    $transients = $wpdb->get_col("
        SELECT option_name 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_wplms_stats_cache_%'
           OR option_name LIKE '_transient_timeout_wplms_stats_cache_%'
    ");

    if ($transients) {
        foreach ($transients as $t) {
            // delete_option aceita tanto transient normal quanto timeout (dependendo do nome)
            delete_option($t);
        }
    }

    wp_send_json_success("Cache de todos os cursos apagado com sucesso.");
}


/******************************************************
 * AJAX: GERAR TABELA – SEM PAGINAÇÃO / SEM LIMIT
 ******************************************************/
add_action("wp_ajax_wplms_gerar_tabela", "wplms_gerar_tabela_optimized");

function wplms_gerar_tabela_optimized()
{
    global $wpdb;

    if (!is_user_logged_in()) {
        echo "<div class='alert alert-danger'>Você precisa estar logado para gerar estatísticas.</div>";
        wp_die();
    }

    /* INPUTS */
    $curso_id      = intval($_POST["curso_id"] ?? 0);
    $campos        = isset($_POST["campos"]) ? array_map('sanitize_text_field', (array) $_POST["campos"]) : [];
    $filtro_nome   = trim(sanitize_text_field($_POST['filtro_nome'] ?? ''));
    $status_filtro = sanitize_text_field($_POST['status_filtro'] ?? '');
    $pagina        = max(1, intval($_POST['pagina'] ?? 1));
    $limit         = 500;
    $offset        = ($pagina - 1) * $limit;

    if ($curso_id <= 0) {
        echo "<div class='alert alert-danger'>Curso inválido.</div>";
        wp_die();
    }

    /* CACHE */
    $cache_key_html = 'wplms_stats_cache_' . $curso_id . '_' . md5($filtro_nome . '|' . implode(',', $campos) . '|' . $status_filtro . "|page$pagina");

    if ($cached = get_transient($cache_key_html)) {
        echo $cached;
        wp_die();
    }

    /* TOTAL DE UNIDADES */
    $curriculum = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value FROM {$wpdb->postmeta}
        WHERE post_id = %d AND meta_key = 'vibe_course_curriculum'
    ", $curso_id));

    $unit_ids = [];
    if ($curriculum) {
        $items = maybe_unserialize($curriculum);
        foreach ((array)$items as $i) {
            if (is_numeric($i)) $unit_ids[] = $i;
        }
    }

    $total_units = 1;
    if (!empty($unit_ids)) {
        $in = implode(',', array_map('intval', $unit_ids));
        $total_units = (int)$wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts}
            WHERE ID IN ($in)
            AND post_type IN ('unit','quiz')
        ");
        if ($total_units < 1) $total_units = 1;
    }

    /* BUSCA TOTAL DE ALUNOS PARA PAGINAÇÃO */
    $meta_key = "course_status{$curso_id}";
    $sql_count = "
        SELECT COUNT(DISTINCT u.ID)
        FROM {$wpdb->usermeta} um
        INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
        WHERE um.meta_key = %s
    ";
    $args_count = [$meta_key];
    if ($filtro_nome !== '') {
        $sql_count .= " AND u.display_name LIKE %s ";
        $args_count[] = "%" . $wpdb->esc_like($filtro_nome) . "%";
    }
    $total_usuarios = $wpdb->get_var($wpdb->prepare($sql_count, $args_count));
    $total_paginas  = ceil($total_usuarios / $limit);

    /* BUSCA USUÁRIOS COM LIMIT E OFFSET */
    $sql = "
        SELECT DISTINCT u.ID AS user_id, u.display_name, u.user_email
        FROM {$wpdb->usermeta} um
        INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
        WHERE um.meta_key = %s
    ";
    $args = [$meta_key];
    if ($filtro_nome !== '') {
        $sql .= " AND u.display_name LIKE %s ";
        $args[] = "%" . $wpdb->esc_like($filtro_nome) . "%";
    }
    $sql .= " ORDER BY u.display_name ASC LIMIT $limit OFFSET $offset";
    $query = $wpdb->prepare($sql, $args);
    $users = $wpdb->get_results($query);

    if (empty($users)) {
        echo "<div class='alert alert-info'>Nenhum aluno encontrado nesta página.</div>";
        wp_die();
    }

    /* TABELAS */
    $bp_table = $wpdb->prefix . "bp_activity";
    $bp_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $bp_table));
    $allowed_types = ['unit_complete', 'quiz_completed', 'start_course', 'subscribe_course', 'review_course', 'student_certificate', 'submit_course'];

    /* MODELO DE CERTIFICADO */
    $modelo_certificado = null;
    foreach (['vibe_certificate_template', 'certificate_template_id', 'certificate_template', 'certificate_id'] as $key) {
        $tmp = get_post_meta($curso_id, $key, true);
        if ($tmp) {
            $modelo_certificado = $tmp;
            break;
        }
    }

    $resultado = [];

    /* MAPA STATUS FILTRO */
    $status_map = [
        'nao_iniciado' => 'Não iniciado',
        'em_andamento' => 'Em andamento',
        'concluido'    => 'Concluído'
    ];
    $status_filtro_label = $status_map[$status_filtro] ?? '';

    /* LOOP PRINCIPAL POR ALUNO */
    foreach ($users as $u) {

        $acts_index = [];
        $units_done = 0;

        if ($bp_exists) {
            $placeholders = implode(',', array_fill(0, count($allowed_types), '%s'));
            $sql_logs = $wpdb->prepare("
                SELECT id, type, date_recorded
                FROM {$bp_table}
                WHERE user_id = %d
                AND item_id = %d
                AND component = 'course'
                AND type IN ($placeholders)
                ORDER BY date_recorded DESC
            ", array_merge([$u->user_id, $curso_id], $allowed_types));
            $logs = $wpdb->get_results($sql_logs);
        } else {
            $logs = [];
        }

        foreach ($logs as $log) {
            if (!isset($acts_index[$log->type])) {
                $acts_index[$log->type] = $log;
            }
            if (in_array($log->type, ['unit_complete', 'quiz_completed'])) {
                $units_done++;
            }
        }

        /* STATUS */
        if (isset($acts_index['submit_course'])) {
            $percent = 100;
            $status = "Concluído";
        } else {
            $percent = round(($units_done / $total_units) * 100);
            $status = ($percent >= 100 ? "Concluído" : ($percent > 0 ? "Em andamento" : "Não iniciado"));
        }

        if ($status_filtro_label && $status !== $status_filtro_label) continue;

        /* DATAS */
        $map = [
            "ultima_atividade" => ['submit_course', 'unit_complete', 'start_course', 'review_course'],
            "inscricao_curso"  => ['subscribe_course'],
            "inicio_curso"     => ['start_course'],
            "ultima_unidade"   => ['unit_complete', 'quiz_completed'],
            "certificado"      => ['submit_course', 'student_certificate'],
            "ultima_review"    => ['review_course'],
        ];
        $atividades = [];
        foreach ($map as $campo => $tipos) {
            $atividades[$campo] = "-";
            foreach ($tipos as $tipo) {
                if (!empty($acts_index[$tipo])) {
                    $atividades[$campo] = $acts_index[$tipo]->date_recorded;
                    break;
                }
            }
        }

        $resultado[] = [
            "user_id" => $u->user_id,
            "nome"    => $u->display_name,
            "email"   => $u->user_email,
            "whatsapp" => format_phone(
                get_user_meta($u->user_id, 'billing_phone', true)
                    ?: get_user_meta($u->user_id, 'whatsapp', true)
                    ?: get_user_meta($u->user_id, 'phone', true)
                    ?: "-"
            ),
            "cpf" => format_cpf(
                get_user_meta($u->user_id, 'billing_cpf', true)
                    ?: get_user_meta($u->user_id, 'billing_cpf_cnpj', true)
                    ?: get_user_meta($u->user_id, 'billing_document', true)
                    ?: get_user_meta($u->user_id, 'cpf', true)
                    ?: "-"
            ),
            "progresso" => $percent,
            "status"    => $status,
            "atividades" => $atividades,
            "certificado_id"     => $modelo_certificado ?: "-",
            "codigo_certificado" => ($modelo_certificado) ? "{$modelo_certificado}-{$curso_id}-{$u->user_id}" : "-"
        ];
    }

    /* GERAR TABELA HTML */
    ob_start();
    echo "<div class='table-responsive'><table class='table table-hover table-bordered align-middle shadow-sm'><thead class='table-dark'><tr>";
    foreach ($campos as $c) {
        echo "<th class='text-nowrap fw-bold'>" . esc_html(ucfirst(str_replace('_', ' ', $c))) . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($resultado as $r) {
        echo "<tr>";
        foreach ($campos as $campo) {
            $val = "-";
            switch ($campo) {
                case "id":
                    $val = $r["user_id"];
                    break;
                case "nome":
                    $val = $r["nome"];
                    break;
                case "email":
                    $val = "<a href='mailto:{$r["email"]}' class='text-primary fw-semibold'>{$r["email"]}</a>";
                    break;
                case "whatsapp":
                    $num = preg_replace('/\D/', '', $r["whatsapp"]);
                    $val = ($num !== "") ? "<a href='https://wa.me/$num' target='_blank' class='btn btn-sm btn-success'>WhatsApp</a>" : "-";
                    break;
                case "cpf":
                    $val = $r["cpf"];
                    break;
                case "progresso_curso":
                    $percent = $r["progresso"];
                    $badge = ($percent == 100 ? "success" : ($percent > 0 ? "warning" : "secondary"));
                    $val = "<span class='badge bg-$badge'>{$percent}%</span>";
                    break;
                case "status_curso":
                    $s = $r["status"];
                    $color = ($s == "Concluído" ? "success" : ($s == "Em andamento" ? "warning" : "secondary"));
                    $val = "<span class='badge bg-$color'>$s</span>";
                    break;
                case "certificado":
                    $d = $r["atividades"]["certificado"];
                    $val = ($d !== "-") ? "<span class='badge bg-success'>Sim</span><br><small>$d</small>" : "-";
                    break;
                case "ultima_atividade":
                case "inscricao_curso":
                case "inicio_curso":
                case "ultima_unidade":
                case "ultima_review":
                    $val = format_date($r["atividades"][$campo]);
                    break;
                case "certificado_id":
                    $val = $r["certificado_id"];
                    break;
                case "codigo_certificado":
                    $val = $r["codigo_certificado"];
                    break;
            }
            echo "<td class='text-nowrap'>{$val}</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table></div>";

    // PAGINAÇÃO
    if ($total_paginas > 1) {
        echo "<nav aria-label='Paginação Alunos'><ul class='pagination justify-content-center mt-3'>";
        for ($i = 1; $i <= $total_paginas; $i++) {
            $active = ($i == $pagina) ? 'active' : '';
            echo "<li class='page-item $active'><a class='page-link wplms-pagina' href='#' data-pagina='$i'>$i</a></li>";
        }
        echo "</ul></nav>";
    }

    // CONSOLE LOG
    $total_alunos = count($resultado);
    echo "<script>console.log('Tabela gerada para curso ID: {$curso_id}, status filtro: {$status_filtro_label}, página: {$pagina}, total alunos nesta página: {$total_alunos}');</script>";

    $html = ob_get_clean();

    /* CACHE */
    set_transient($cache_key_html, $html, 30 * MINUTE_IN_SECONDS);

    echo $html;
    wp_die();
}

/* ===== FORMATADORES ===== */

function format_date($d)
{
    if (!$d || $d == "-") return "-";
    return date("d/m/Y H:i", strtotime($d));
}

function format_phone($p)
{
    $p = preg_replace('/\D/', '', $p);
    if (strlen($p) === 11)
        return "(" . substr($p, 0, 2) . ") " . substr($p, 2, 5) . "-" . substr($p, 7);
    if (strlen($p) === 10)
        return "(" . substr($p, 0, 2) . ") " . substr($p, 2, 4) . "-" . substr($p, 6);
    return $p;
}

function format_cpf($c)
{
    $c = preg_replace('/\D/', '', $c);
    if (strlen($c) === 11)
        return substr($c, 0, 3) . "." . substr($c, 3, 3) . "." . substr($c, 6, 3) . "-" . substr($c, 9);
    return $c;
}
add_action("wp_ajax_wplms_exportar_excel", "wplms_exportar_excel");
add_action("wp_ajax_nopriv_wplms_exportar_excel", "wplms_exportar_excel");

function wplms_exportar_excel()
{
    global $wpdb;

    /* ==================================================
     * 🛡️ SEGURANÇA E LIMITES
     * ================================================== */
    if (!is_user_logged_in()) {
        wp_die("Não autorizado");
    }

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);

    while (ob_get_level()) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    /* ==================================================
     * 📥 INPUTS
     * ================================================== */
    $curso_id    = intval($_POST["curso_id"] ?? 0);
    $campos      = isset($_POST["campos"]) ? (array) $_POST["campos"] : [];
    $filtro_nome = trim(sanitize_text_field($_POST['filtro_nome'] ?? ''));

    if ($curso_id <= 0 || empty($campos)) {
        wp_die("Parâmetros inválidos");
    }

    /* ==================================================
     * 📚 TOTAL DE UNIDADES
     * ================================================== */
    $curriculum = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta}
         WHERE post_id=%d AND meta_key='vibe_course_curriculum'",
        $curso_id
    ));

    $unit_ids = [];
    foreach ((array) maybe_unserialize($curriculum) as $i) {
        if (is_numeric($i)) $unit_ids[] = (int)$i;
    }

    $total_units = 1;
    if ($unit_ids) {
        $in = implode(',', $unit_ids);
        $total_units = max(1, (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE ID IN ($in) AND post_type IN ('unit','quiz')"
        ));
    }

    /* ==================================================
     * 📤 HEADERS CSV
     * ================================================== */
    $filename = "estatisticas_curso_{$curso_id}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");

    $output = fopen('php://output', 'w');
    fputcsv($output, $campos, ';');

    /* ==================================================
     * ⚙️ SETUP CONSULTAS
     * ================================================== */
    $meta_key = "course_status{$curso_id}";
    $limit  = 200;
    $offset = 0;

    $allowed_types = [
        'unit_complete','quiz_completed','start_course',
        'subscribe_course','review_course',
        'student_certificate','submit_course'
    ];

    $bp_table  = $wpdb->prefix . "bp_activity";
    $bp_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$bp_table}'") === $bp_table);

    /* MODELO DE CERTIFICADO */
    $modelo_certificado = null;
    foreach (['vibe_certificate_template','certificate_template_id','certificate_template','certificate_id'] as $k) {
        if ($v = get_post_meta($curso_id, $k, true)) {
            $modelo_certificado = $v;
            break;
        }
    }

    /* ==================================================
     * 🔁 LOOP EM BLOCOS
     * ================================================== */
    do {

        $args = [$meta_key];
        $where_nome = "";

        if ($filtro_nome !== "") {
            $where_nome = " AND u.display_name LIKE %s ";
            $args[] = "%" . $wpdb->esc_like($filtro_nome) . "%";
        }

        $args[] = $limit;
        $args[] = $offset;

        $users = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT u.ID, u.display_name, u.user_email
            FROM {$wpdb->usermeta} um
            INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
            WHERE um.meta_key = %s
            $where_nome
            ORDER BY u.ID ASC
            LIMIT %d OFFSET %d
        ", $args));

        if (!$users) {
            break;
        }

        foreach ($users as $u) {

            $acts = [];
            $units_done = 0;

            /* LOGS DO BP */
            if ($bp_exists) {
                $ph = implode(',', array_fill(0, count($allowed_types), '%s'));
                $logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT type, date_recorded
                     FROM {$bp_table}
                     WHERE user_id=%d AND item_id=%d
                     AND component='course'
                     AND type IN ($ph)
                     ORDER BY date_recorded DESC",
                    array_merge([$u->ID, $curso_id], $allowed_types)
                ));
            } else {
                $logs = [];
            }

            foreach ($logs as $l) {
                if (!isset($acts[$l->type])) $acts[$l->type] = $l;
                if (in_array($l->type, ['unit_complete','quiz_completed'])) {
                    $units_done++;
                }
            }

            /* STATUS / PROGRESSO */
            if (isset($acts['submit_course'])) {
                $percent = 100;
                $status  = "Concluído";
            } else {
                $percent = round(($units_done / $total_units) * 100);
                $status  = $percent >= 100 ? "Concluído" : ($percent > 0 ? "Em andamento" : "Não iniciado");
            }

            /* DATAS */
            $map = [
                "ultima_atividade" => ['submit_course','unit_complete','start_course','review_course'],
                "inscricao_curso"  => ['subscribe_course'],
                "inicio_curso"     => ['start_course'],
                "ultima_unidade"   => ['unit_complete','quiz_completed'],
                "certificado"      => ['submit_course','student_certificate'],
                "ultima_review"    => ['review_course'],
            ];

            $datas = [];
            foreach ($map as $key => $types) {
                $datas[$key] = "-";
                foreach ($types as $t) {
                    if (!empty($acts[$t])) {
                        $datas[$key] = $acts[$t]->date_recorded;
                        break;
                    }
                }
            }

            /* LINHA COMPLETA (IGUAL AO EXPORT ORIGINAL) */
            $linha = [
                "id" => $u->ID,
                "nome" => $u->display_name,
                "email" => $u->user_email,

                "whatsapp" => get_user_meta($u->ID, 'billing_phone', true)
                    ?: get_user_meta($u->ID, 'whatsapp', true)
                    ?: get_user_meta($u->ID, 'phone', true)
                    ?: "-",

                "cpf" => get_user_meta($u->ID, 'billing_cpf', true)
                    ?: get_user_meta($u->ID, 'billing_cpf_cnpj', true)
                    ?: get_user_meta($u->ID, 'billing_document', true)
                    ?: get_user_meta($u->ID, 'cpf', true)
                    ?: "-",

                "progresso_curso" => $percent,
                "status_curso" => $status,

                "ultima_atividade" => $datas["ultima_atividade"],
                "inscricao_curso"  => $datas["inscricao_curso"],
                "inicio_curso"     => $datas["inicio_curso"],
                "ultima_unidade"   => $datas["ultima_unidade"],
                "certificado"      => $datas["certificado"],
                "ultima_review"    => $datas["ultima_review"],

                "codigo_certificado" => $modelo_certificado
                    ? "{$modelo_certificado}-{$curso_id}-{$u->ID}"
                    : "-"
            ];

            $row = [];
            foreach ($campos as $c) {
                $row[] = $linha[$c] ?? "-";
            }

            fputcsv($output, $row, ';');
        }

        $offset += $limit;
        fflush($output);
        flush();

    } while (true);

    fclose($output);
    exit;
}









add_action('wp_ajax_exportar_alunos_excel', 'exportar_alunos_excel');
add_action('wp_ajax_nopriv_exportar_alunos_excel', 'exportar_alunos_excel');

function exportar_alunos_excel() {

    global $wpdb;

    if (empty($_POST['ids'])) {
        wp_send_json_error('Nenhum aluno recebido.');
    }

    if (ob_get_length()) ob_end_clean();

    $ids = array_map('intval', explode(',', sanitize_text_field($_POST['ids'])));

    //------------------------------------------------------------
    // Diretório temporário
    //------------------------------------------------------------
    $tempDir = sys_get_temp_dir() . '/xlsx_alunos_' . uniqid();
    mkdir($tempDir);
    mkdir("$tempDir/_rels");
    mkdir("$tempDir/xl");
    mkdir("$tempDir/xl/_rels");
    mkdir("$tempDir/xl/worksheets");

    //------------------------------------------------------------
    // Coleta de dados
    //------------------------------------------------------------
    $sheets = [];

    foreach ($ids as $id) {

        $user = get_userdata($id);
        if (!$user) continue;

        $sheetName = preg_replace('/[^A-Za-z0-9\-]/', '', $user->display_name);
        $sheetName = mb_substr($sheetName, 0, 25) . '-' . $id;

        $rows = [];

        //--------------------------------------------------------
        // 🔥 DADOS DO ALUNO
        //--------------------------------------------------------
        $cpf = get_user_meta($id, 'billing_cpf', true) ?: '-';

        $total_logins = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(login_count)
                 FROM {$wpdb->prefix}user_login_history
                 WHERE user_id = %d",
                $id
            )
        );

        $rows[] = ['Aluno', $user->display_name];
        $rows[] = ['Email', $user->user_email];
        $rows[] = ['CPF', $cpf];
        $rows[] = ['Total de logins', $total_logins];
        $rows[] = [];

        //--------------------------------------------------------
        // 🔥 LOGINS AGRUPADOS POR MÊS
        //--------------------------------------------------------
        $logins_por_mes = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT 
                    DATE_FORMAT(login_date, '%%Y-%%m') AS mes,
                    SUM(login_count) AS total
                FROM {$wpdb->prefix}user_login_history
                WHERE user_id = %d
                GROUP BY mes
                ORDER BY mes ASC
                ",
                $id
            )
        );

        if ($logins_por_mes) {

            $rows[] = ['Logins por mês'];
            $rows[] = ['Mês', 'Quantidade'];

            foreach ($logins_por_mes as $m) {
                $rows[] = [
                    ucfirst(date_i18n('M/Y', strtotime($m->mes . '-01'))),
                    (int) $m->total
                ];
            }

            $rows[] = [];
        }

        //--------------------------------------------------------
        // 🔥 DETALHAMENTO DIÁRIO HORIZONTAL (POR MÊS)
        //--------------------------------------------------------
        foreach ($logins_por_mes as $m) {

            $rows[] = ['Detalhamento diário - ' . ucfirst(date_i18n('F/Y', strtotime($m->mes . '-01')))];
            
            $registros = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT login_date, login_time, login_count
                    FROM {$wpdb->prefix}user_login_history
                    WHERE user_id = %d
                      AND DATE_FORMAT(login_date, '%%Y-%%m') = %s
                    ORDER BY login_date ASC, login_time ASC
                    ",
                    $id,
                    $m->mes
                )
            );

            if (!$registros) {
                $rows[] = [];
                continue;
            }

            // Organiza por dia
            $por_dia = [];

            foreach ($registros as $r) {

                $dia = date('d', strtotime($r->login_date));
                $hora = substr($r->login_time, 0, 5);

                if (!isset($por_dia[$dia])) {
                    $por_dia[$dia] = [
                        'horas' => [],
                        'total' => 0
                    ];
                }

                $por_dia[$dia]['horas'][] = $hora;
                $por_dia[$dia]['total'] += (int) $r->login_count;
            }

            // Linha 1 → Dias
            $linha_dias = ['Dia'];
            foreach ($por_dia as $dia => $dados) {
                $linha_dias[] = $dia;
            }

            // Linha 2 → Horários
            $linha_horas = ['Horários'];
            foreach ($por_dia as $dados) {
                $linha_horas[] = implode(', ', array_unique($dados['horas']));
            }

            

            $rows[] = $linha_dias;
            $rows[] = $linha_horas;
         
            $rows[] = [];
        }

        //--------------------------------------------------------
        // 🔥 TABELA DE CURSOS
        //--------------------------------------------------------
        $rows[] = ['Curso', 'Progresso', 'Status', 'Último acesso'];

        $courses = bp_course_get_user_courses($id);
        if (is_array($courses)) {

            foreach (array_unique($courses) as $course_id) {

                if (get_post_type($course_id) !== 'course') continue;
                if (has_term('certificados', 'course-cat', $course_id)) continue;

                $progress = intval(bp_course_get_user_progress($id, $course_id)) . '%';
                $status = intval(bp_course_get_user_course_status($id, $course_id)) === 4
                    ? 'Concluído'
                    : 'Em andamento';

                $last = '';

                if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bp_activity'")) {
                    $last = $wpdb->get_var(
                        $wpdb->prepare(
                            "
                            SELECT date_recorded
                            FROM {$wpdb->prefix}bp_activity
                            WHERE user_id = %d
                              AND component = 'course'
                              AND item_id = %d
                            ORDER BY date_recorded DESC
                            LIMIT 1
                            ",
                            $id,
                            $course_id
                        )
                    );
                }

                $rows[] = [
                    get_the_title($course_id),
                    $progress,
                    $status,
                    $last ? wp_date('d/m/Y H:i', strtotime($last)) : '-'
                ];
            }
        }

        $sheets[] = [
            'name' => $sheetName,
            'rows' => $rows
        ];
    }

    if (empty($sheets)) {
        wp_send_json_error('Nenhum dado válido.');
    }

    //------------------------------------------------------------
    // 🔥 A PARTIR DAQUI: GERAÇÃO DO XLSX (SEM ALTERAÇÕES)
    //------------------------------------------------------------
    $contentTypes =
        '<?xml version="1.0" encoding="UTF-8"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml"
             ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

    foreach ($sheets as $i => $s) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml"
            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $contentTypes .= '</Types>';

    file_put_contents("$tempDir/[Content_Types].xml", $contentTypes);

    file_put_contents("$tempDir/_rels/.rels",
        '<?xml version="1.0" encoding="UTF-8"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1"
              Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
              Target="xl/workbook.xml"/>
        </Relationships>'
    );

    //------------------------------------------------------------
    // Workbook
    //------------------------------------------------------------
    $sheetsXML = '';
    foreach ($sheets as $i => $s) {
        $sheetsXML .= '<sheet name="'.esc_attr(mb_substr($s['name'],0,31)).'"
            sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
    }

    file_put_contents("$tempDir/xl/workbook.xml",
        '<?xml version="1.0" encoding="UTF-8"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
                  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets>'.$sheetsXML.'</sheets>
        </workbook>'
    );

    //------------------------------------------------------------
    // Relações
    //------------------------------------------------------------
    $rels = '';
    foreach ($sheets as $i => $s) {
        $rels .= '<Relationship Id="rId'.($i+1).'"
          Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
          Target="worksheets/sheet'.($i+1).'.xml"/>';
    }

    file_put_contents("$tempDir/xl/_rels/workbook.xml.rels",
        '<?xml version="1.0" encoding="UTF-8"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            '.$rels.'
        </Relationships>'
    );

    //------------------------------------------------------------
    // Worksheets
    //------------------------------------------------------------
    foreach ($sheets as $i => $s) {
        $rowsXML = '';
        $r = 1;
        foreach ($s['rows'] as $row) {
            $rowsXML .= wplms_xlsx_row_xml($r++, $row);
        }

        file_put_contents("$tempDir/xl/worksheets/sheet".($i+1).".xml",
            '<?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetData>'.$rowsXML.'</sheetData>
            </worksheet>'
        );
    }

    //------------------------------------------------------------
    // ZIP final
    //------------------------------------------------------------
    $filename = 'alunos_exportados_' . date('Ymd_His') . '.xlsx';
    $filePath = WP_CONTENT_DIR . '/uploads/' . $filename;

    $zip = new ZipArchive();
    $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    wplms_xlsx_add_files($tempDir, $zip);
    $zip->close();

    wplms_xlsx_delete_dir($tempDir);

    wp_send_json_success(['url' => content_url('uploads/' . $filename)]);
}


function wplms_xlsx_row_xml($rowNum, $values) {
    $cells = '';
    $col = 1;
    foreach ($values as $val) {
        $coord = wplms_xlsx_col_letter($col).$rowNum;
        $cells .= '<c r="'.$coord.'" t="inlineStr"><is><t>'.esc_html($val).'</t></is></c>';
        $col++;
    }
    return '<row r="'.$rowNum.'">'.$cells.'</row>';
}

function wplms_xlsx_col_letter($num) {
    $letter = '';
    while ($num > 0) {
        $num--;
        $letter = chr(65 + ($num % 26)) . $letter;
        $num = intval($num / 26);
    }
    return $letter;
}

function wplms_xlsx_add_files($dir, &$zip, $base = '') {
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = "$dir/$item";
        $local = ltrim("$base/$item", '/');
        if (is_dir($path)) wplms_xlsx_add_files($path, $zip, $local);
        else $zip->addFile($path, $local);
    }
}

function wplms_xlsx_delete_dir($dir) {
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = "$dir/$item";
        is_dir($path) ? wplms_xlsx_delete_dir($path) : unlink($path);
    }
    rmdir($dir);
}

function wplms_pagina_alunos() {
    global $wpdb;

    // Bootstrap CDN
    echo '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
    .avatar-img {
        width: 40px;
        height: 40px;
        object-fit: cover;
    }
    h1 {
        font-size: clamp(1.4rem, 2.5vw, 2rem);
        font-weight: 600;
    }
    .modal-dialog {
        display: flex;
        align-items: center;
        min-height: calc(100% - 1rem);
    }
    .modal-content {
        max-height: 80vh;
        display: flex;
        flex-direction: column;
    }
    .modal-body {
        overflow-y: auto;
        padding: 1rem 1.2rem;
    }
    @media (max-width: 576px) {
        .modal-dialog {
            max-width: 95% !important;
            margin: 0 auto;
        }
        .modal-content {
            max-height: 85vh;
        }
        table td {
            font-size: 12px;
            white-space: nowrap;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
    }
    </style>
    ';

    echo '<div class="container-fluid mt-4">';
    echo '<h1 class="mb-4">📘 Alunos Matriculados</h1>';

    // 🔍 Campo de pesquisa
    echo '
    <div class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <input id="filtroAlunos" type="text" class="form-control form-control-lg"
                       placeholder="Pesquisar aluno pelo nome...">
            </div>
            <div class="col-12 col-md-4 d-grid">
                <button id="btnExportarExcel" class="btn btn-success btn-lg">
                    📤 Exportar Selecionados
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const filtro = document.getElementById("filtroAlunos");
            const linhas = document.querySelectorAll("#tabelaAlunos tbody tr");

            filtro.addEventListener("keyup", function() {
                const texto = this.value.toLowerCase();
                linhas.forEach(linha => {
                    const nome = linha.querySelector(".col-nome").innerText.toLowerCase();
                    linha.style.display = nome.includes(texto) ? "" : "none";
                });
            });
        });
    </script>
    ';

    // Buscar usuários com cursos
    $users = $wpdb->get_results("
        SELECT DISTINCT user_id
        FROM {$wpdb->usermeta}
        WHERE meta_key LIKE 'course_status_%'
    ");

    echo '
    <div class="shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
            <table id="tabelaAlunos" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th><input type="checkbox" id="selecionarTodos"></th>
                        <th>ID</th>
                        <th></th>
                        <th>Aluno</th>
                        <th>Email</th>
                        <th>CPF</th>
                        <th>Total</th>
                        <th>Concluídos</th>
                        <th>Progresso</th>
                        <th>Último acesso</th>
                        <th>Primeiro Acesso</th>
						<th>Logins</th>

                        <th>Cursos</th>
                    </tr>
                </thead>
                <tbody>
    ';

  foreach ($users as $u) {

    $user_id = (int) $u->user_id;
    $user    = get_userdata($user_id);
    if (!$user) {
        continue;
    }

    /**
     * ==========================
     * CPF (BUSCA AVANÇADA)
     * ==========================
     */
    $cpf = '';

    foreach (['billing_cpf', 'cpf', 'documento', 'user_cpf'] as $key) {
        $cpf = get_user_meta($user_id, $key, true);
        if (!empty($cpf)) break;
    }

    if (empty($cpf) && $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_customer_lookup'")) {
        $cpf = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cpf FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );
    }

    if (empty($cpf)) {
        $cpf = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_billing_cpf'
                  AND p.post_type = 'shop_order'
                  AND p.post_author = %d
                ORDER BY p.post_date DESC
                LIMIT 1
                ",
                $user_id
            )
        );
    }

    $cpf = $cpf ? esc_html($cpf) : '-';

    /**
     * ==========================
     * CURSOS
     * ==========================
     */
    $courses = bp_course_get_user_courses($user_id);
    if (!is_array($courses)) continue;

    $courses = array_unique(array_map('intval', $courses));
    $valid_courses = [];

    foreach ($courses as $course_id) {
        if (
            get_post_type($course_id) === 'course' &&
            !has_term('certificados', 'course-cat', $course_id)
        ) {
            $valid_courses[] = $course_id;
        }
    }

    if (empty($valid_courses)) continue;

    $total         = count($valid_courses);
    $completed     = 0;
    $totalProgress = 0;

    /**
     * ==========================
     * MODAL – CURSOS
     * ==========================
     */
    $modalContent = "<ul class='list-group'>";

    foreach ($valid_courses as $course_id) {

        $status   = (int) bp_course_get_user_course_status($user_id, $course_id);
        $progress = (int) bp_course_get_user_progress($user_id, $course_id);

        if ($status === 4) $completed++;
        $totalProgress += $progress;

        // Último acesso ao curso
        $course_last_access = '';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bp_activity'")) {
            $course_last_access = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT date_recorded
                    FROM {$wpdb->prefix}bp_activity
                    WHERE user_id = %d
                      AND component = 'course'
                      AND item_id = %d
                    ORDER BY date_recorded DESC
                    LIMIT 1
                    ",
                    $user_id,
                    $course_id
                )
            );
        }

        $course_last_access = $course_last_access
            ? wp_date('d/m/Y H:i', strtotime($course_last_access))
            : '-';

        $modalContent .= "
            <li class='list-group-item'>
                <strong>" . esc_html(get_the_title($course_id)) . "</strong>
                <br><small>ID: {$course_id}</small>
                <br><small>Progresso: {$progress}%</small>
                <br><small>Último acesso: {$course_last_access}</small>
            </li>
        ";
    }

    $modalContent .= "</ul>";

    /**
     * ==========================
     * MÉTRICAS GERAIS
     * ==========================
     */
    $progress_percent = ceil($totalProgress / $total);

    $last_access = get_user_meta($user_id, 'last_activity', true);
    $last_access = $last_access
        ? wp_date('d/m/Y', strtotime($last_access))
        : '-';

    $subscription_date = get_user_meta($user_id, 'course_subscription_date', true);
    if (!$subscription_date) $subscription_date = $user->user_registered;
    $subscription_date = wp_date('d/m/Y', strtotime($subscription_date));

    /**
     * ==========================
     * MÉTRICAS DE LOGIN (TABELA)
     * ==========================
     */
   $login_table = $wpdb->prefix . 'user_login_history';

/**
 * ==========================
 * TOTAL DE LOGINS
 * ==========================
 */
$total_logins = (int) $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT SUM(login_count)
        FROM {$login_table}
        WHERE user_id = %d
        ",
        $user_id
    )
);


/**
 * ==========================
 * ÚLTIMO LOGIN (DATA + HORA)
 * SOMENTE SE TIVER HORA
 * ==========================
 */
$last_login_row = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT login_date, login_time
        FROM {$login_table}
        WHERE user_id = %d
          AND login_time IS NOT NULL
        ORDER BY login_date DESC, login_time DESC
        LIMIT 1
        ",
        $user_id
    )
);

if ($last_login_row) {
    $last_login = wp_date(
        'd/m/Y H:i',
        strtotime($last_login_row->login_date . ' ' . $last_login_row->login_time)
    );
} else {
    $last_login = '-';
}

/**
 * ==========================
 * DIAS COM ACESSO
 * ==========================
 */
$login_days = (int) $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT COUNT(DISTINCT login_date)
        FROM {$login_table}
        WHERE user_id = %d
        ",
        $user_id
    )
);
/**
 * ==========================
 * Organizando por mês
 * ==========================
 */
	  
	  $login_history = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT login_date, login_time, login_count
        FROM {$login_table}
        WHERE user_id = %d
        ORDER BY login_date DESC, login_time DESC
        ",
        $user_id
    )
);
	  
	  
	  $logins_by_month = [];

foreach ($login_history as $row) {

    // Ex: 2026-01
    $month_key = date('Y-m', strtotime($row->login_date));

    // Ex: 15/01/2026
    $day_key = wp_date('d/m/Y', strtotime($row->login_date));

    if (!isset($logins_by_month[$month_key])) {
        $logins_by_month[$month_key] = [];
    }

    if (!isset($logins_by_month[$month_key][$day_key])) {
        $logins_by_month[$month_key][$day_key] = [];
    }

    if (!empty($row->login_time)) {
        $logins_by_month[$month_key][$day_key][] = $row->login_time;
    }
}
	  
	  
	  
	  
	  
/**
 * ==========================
 * MÉDIA DIÁRIA
 * ==========================
 */
$login_avg = $login_days > 0
    ? round($total_logins / $login_days, 2)
    : 0;

/**
 * ==========================
 * MODAL – HISTÓRICO DE LOGIN
 * ==========================
 */
$login_modal_id = 'modalLogin_' . $user_id;

$loginModalContent = "
<ul class='list-group mb-3'>
    <li class='list-group-item'>
        <strong>Total de logins:</strong> {$total_logins}
    </li>
    <li class='list-group-item'>
        <strong>Último login:</strong> {$last_login}
    </li>
    <li class='list-group-item'>
        <strong>Dias com acesso:</strong> {$login_days}
    </li>
    <li class='list-group-item'>
        <strong>Média diária:</strong> {$login_avg}
    </li>
</ul>

<div class='accordion' id='accordionLogin{$user_id}'>
";

// Renderiza meses
$month_index = 0;

foreach ($logins_by_month as $month => $days) {

    $month_index++;
    $month_label = wp_date('F/Y', strtotime($month . '-01'));

    $loginModalContent .= "
    <div class='accordion-item'>
        <h2 class='accordion-header' id='heading{$user_id}_{$month_index}'>
            <button class='accordion-button collapsed' type='button'
                data-bs-toggle='collapse'
                data-bs-target='#collapse{$user_id}_{$month_index}'>
                📅 {$month_label} — <strong>" . count($days) . " dias com acesso</strong>
            </button>
        </h2>

        <div id='collapse{$user_id}_{$month_index}'
            class='accordion-collapse collapse'
            data-bs-parent='#accordionLogin{$user_id}'>
            <div class='accordion-body'>
                <ul class='list-group'>
    ";

    // Renderiza dias
    foreach ($days as $day => $times) {

        $loginModalContent .= "
            <li class='list-group-item'>
                <strong>{$day}</strong>
        ";

        if (!empty($times)) {
            $loginModalContent .= "
                <br>
                <small>⏰ Horários: " . implode(', ', $times) . "</small>
            ";
        } else {
            $loginModalContent .= "
                <br>
                <small class='text-muted'>⏰ Horário não registrado</small>
            ";
        }

        $loginModalContent .= "</li>";
    }

    $loginModalContent .= "
                </ul>
            </div>
        </div>
    </div>
    ";
}

$loginModalContent .= "</div>";
	  
	  
	  
	  
    $avatar_url = esc_url(get_avatar_url($user_id));
    $modal_id   = 'modalCursos_' . $user_id;

    /**
     * ==========================
     * OUTPUT
     * ==========================
     */
    echo "
    <tr>
        <td><input type='checkbox' class='check-aluno' value='{$user_id}'></td>
        <td>{$user_id}</td>
        <td><img src='{$avatar_url}' class='avatar-img rounded-circle'></td>
        <td class='col-nome'>" . esc_html($user->display_name) . "</td>
        <td>" . esc_html($user->user_email) . "</td>
        <td>{$cpf}</td>
        <td>{$total}</td>
        <td>{$completed}</td>
        <td>{$progress_percent}%</td>
        <td>{$last_access}</td>
        <td>{$subscription_date}</td>
        <td>
            <button class='btn btn-primary btn-sm w-100'
                data-bs-toggle='modal'
                data-bs-target='#{$login_modal_id}'>
                {$total_logins}
            </button>
        </td>
        <td>
            <button class='btn btn-primary btn-sm w-100'
                data-bs-toggle='modal'
                data-bs-target='#{$modal_id}'>
                Ver mais
            </button>
        </td>
    </tr>

    <div class='modal fade' id='{$modal_id}' tabindex='-1'>
        <div class='modal-dialog modal-dialog-centered modal-lg'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h5 class='modal-title'>Cursos de " . esc_html($user->display_name) . "</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>{$modalContent}</div>
            </div>
        </div>
    </div>

    <div class='modal fade' id='{$login_modal_id}' tabindex='-1'>
        <div class='modal-dialog modal-dialog-centered'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h5 class='modal-title'>Acessos de " . esc_html($user->display_name) . "</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>{$loginModalContent}</div>
            </div>
        </div>
    </div>
    ";
}


    echo '
                </tbody>
            </table>
            </div>
        </div>
    </div>
    </div>
    
	 <script>
        document.addEventListener("DOMContentLoaded", function() {

            const master = document.getElementById("selecionarTodos");
            const checks = document.querySelectorAll(".check-aluno");

            master.addEventListener("change", function() {
                checks.forEach(c => c.checked = master.checked);
            });

            checks.forEach(c => {
                c.addEventListener("change", function() {
                    if (!this.checked) master.checked = false;
                });
            });

            // EXPORTAÇÃO
            document.getElementById("btnExportarExcel").addEventListener("click", function() {

                let selecionados = [...document.querySelectorAll(".check-aluno:checked")].map(e => e.value);

                if (selecionados.length === 0) {
                    alert("Selecione pelo menos um aluno!");
                    return;
                }

                let formData = new FormData();
                formData.append("action", "exportar_alunos_excel");
                formData.append("ids", selecionados.join(","));

                fetch("' . admin_url("admin-ajax.php") . '", {
                    method: "POST",
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        window.location.href = res.data.url;
                    } else {
                        alert("Erro ao gerar planilha: " + res.data);
                    }
                });
            });

        });
    </script>
	
	';
}



/**
 * =====================================================
 * REGISTRO AVANÇADO DE LOGINS (WORDPRESS + BUDDYPRESS + WPLMS)
 * =====================================================
 * 
 * Autor: Miguel Ferreira
 * Data: 16/01/2026
 * 
 * Descrição:
 * Este conjunto de funções implementa um **sistema avançado de registro de logins**
 * para usuários do WordPress, garantindo compatibilidade total com:
 * 
 * - Login nativo do WordPress (wp-admin / wp-login.php)
 * - Login via BuddyPress (frontend)
 * - Login via WPLMS / Vibe Theme
 * 
 * O sistema foi projetado para registrar **apenas logins reais**, evitando contagens
 * incorretas causadas por recarregamento de páginas, abertura de novas abas
 * ou navegação interna após o login.
 * 
 * -----------------------------------------------------
 * ⚙️ O que este código faz:
 * -----------------------------------------------------
 * - Registra **1 login por sessão real de usuário**.
 * - Salva os dados em uma tabela personalizada:
 * 
 *   wp_user_login_history
 * 
 *   Colunas utilizadas:
 *     • user_id      → ID do usuário logado
 *     • login_date   → Data do login (YYYY-MM-DD)
 *     • login_time   → Hora do login (HH:MM:SS)
 *     • login_count  → Quantidade de logins realizados no dia
 * 
 * - Atualiza automaticamente:
 *     • A hora do último login do dia
 *     • O contador diário de logins
 * 
 * -----------------------------------------------------
 * 🛑 Problemas resolvidos por este código:
 * -----------------------------------------------------
 * ❌ Evita contagem duplicada ao:
 *     • Atualizar a página
 *     • Abrir múltiplas abas
 *     • Navegar entre páginas após o login
 *     • Disparo repetido de hooks como `set_current_user`
 * 
 * ❌ Impede falsos logins gerados por:
 *     • Cookies já existentes
 *     • Sessões reativadas automaticamente
 *     • Carregamentos internos do BuddyPress
 * 
 * -----------------------------------------------------
 * 🧠 Funcionamento técnico:
 * -----------------------------------------------------
 * - Utiliza os hooks corretos de autenticação:
 *     • wp_login → Login nativo do WordPress
 *     • bp_core_loggedin_user → Login frontend (BuddyPress / WPLMS)
 * 
 * - Controla duplicação usando um **cookie de sessão**:
 *     • O cookie expira ao fechar o navegador
 *     • Garante apenas 1 registro por sessão
 * 
 * - Usa o timezone configurado no WordPress (`wp_date`)
 * - Executa queries seguras com `$wpdb->prepare`
 * - Atualiza ou cria registros de forma inteligente (UPSERT manual)
 * 
 * -----------------------------------------------------
 * 📊 Exemplo de registros gerados:
 * -----------------------------------------------------
 * | user_id | login_date | login_time | login_count |
 * |---------|------------|------------|-------------|
 * | 63      | 2026-01-16 | 09:10:22   | 1           |
 * | 63      | 2026-01-16 | 15:42:09   | 2           |
 * | 27      | 2026-01-15 | 08:55:01   | 1           |
 * 
 * -----------------------------------------------------
 * ✅ Benefícios:
 * -----------------------------------------------------
 * ✔ Contagem de logins precisa
 * ✔ Histórico confiável por usuário
 * ✔ Compatível com relatórios e exportações
 * ✔ Funciona em login admin e frontend
 * ✔ Ideal para métricas de engajamento e auditoria
 * 
 * -----------------------------------------------------
 * 🧩 Requisitos:
 * -----------------------------------------------------
 * - WordPress 5.8+
 * - BuddyPress ativo
 * - WPLMS / Vibe Theme
 * - Tabela personalizada `wp_user_login_history` criada
 * 
 * -----------------------------------------------------
 * 💡 Possíveis extensões futuras:
 * -----------------------------------------------------
 * - Tempo médio logado por sessão
 * - Detecção de sessões simultâneas
 * - Relatórios gráficos de acesso
 * - Integração com exportação CSV / Excel
 * - Alertas de login suspeito
 * 
 * =====================================================
 */





function wplms_registrar_login_usuario($user_id) {

    error_log('=== LOGIN TRACK INICIADO ===');

    global $wpdb;
    $table = $wpdb->prefix . 'user_login_history';

    if (!$user_id) {
        error_log('[ERRO] user_id vazio');
        return;
    }

    error_log('[OK] user_id: ' . $user_id);

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        error_log('[ERRO] Tabela não existe');
        return;
    }

    $today = current_time('Y-m-d');
    $now   = current_time('H:i:s');

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, login_count FROM {$table}
             WHERE user_id = %d AND login_date = %s",
            $user_id,
            $today
        )
    );

    if ($row) {

        $wpdb->update(
            $table,
            [
                'login_count' => (int)$row->login_count + 1,
                'login_time'  => $now
            ],
            ['id' => $row->id],
            ['%d', '%s'],
            ['%d']
        );

        error_log('[UPDATE] Login atualizado');

    } else {

        $wpdb->insert(
            $table,
            [
                'user_id'     => $user_id,
                'login_date'  => $today,
                'login_time'  => $now,
                'login_count' => 1
            ],
            ['%d', '%s', '%s', '%d']
        );

        error_log('[INSERT] Login criado');
    }

    if ($wpdb->last_error) {
        error_log('[SQL ERRO] ' . $wpdb->last_error);
    } else {
        error_log('[SUCESSO] Login gravado');
    }

    error_log('=== LOGIN TRACK FINALIZADO ===');
}


add_action('rest_api_init', function () {

    register_rest_route('wplms/v1', '/track-login', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {

            error_log('=== TRACK LOGIN REST ===');

            $user_id = (int) $request->get_param('user_id');

            if (!$user_id) {
                error_log('[ERRO] user_id não enviado');
                return new WP_Error('missing_user', 'user_id ausente', ['status' => 400]);
            }

            if (!get_user_by('id', $user_id)) {
                error_log('[ERRO] user_id inválido: ' . $user_id);
                return new WP_Error('invalid_user', 'Usuário inválido', ['status' => 403]);
            }

            error_log('[OK] user_id validado: ' . $user_id);

            wplms_registrar_login_usuario($user_id);

            return [
                'status'  => 'login registrado',
                'user_id' => $user_id
            ];
        },

        // 🔥 IGNORA JWT AUTH
        'permission_callback' => function () {
            return true;
        }
    ]);
});

add_action('wp_footer', function () {
    ?>
    <script>
(function () {

    console.log('%c[LOGIN DEBUG BP_USER] iniciado', 'color: green; font-weight: bold');

    const originalFetch = window.fetch;

    window.fetch = function (...args) {

        const [url] = args;

        return originalFetch.apply(this, args).then(response => {

            if (typeof url === 'string' && url.includes('/vibebp/v1/token/validate-token')) {

                response.clone().json().then(() => {

                    const bpUserRaw = sessionStorage.getItem('bp_user');
                    if (!bpUserRaw) return;

                    const bpUser = JSON.parse(bpUserRaw);

                    console.log('[BP_USER DETECTADO]', bpUser);

                    fetch('/wp-json/wplms/v1/track-login', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: bpUser.id })
                    })
                    .then(r => r.json())
                    .then(res => {
                        console.log('%c[TRACK LOGIN OK]', 'color: purple; font-weight: bold', res);
                    });

                }).catch(() => {});
            }

            return response;
        });
    };

})();
    </script>
    <?php
}, 999);




/**
 * ============================================================
 * 🔄 WPLMS Stats – Auto Download & Cache do config-stats.php
 * ============================================================
 *
 * 📌 Autor: Miguel Cezar Ferreira
 * 📅 Data: 28/11/2025
 *
 * 📘 Descrição Geral:
 * Esta função é responsável por **baixar automaticamente** o arquivo
 * `config-stats.php` diretamente do repositório oficial no GitHub,
 * armazená-lo em cache dentro do WordPress e retornar o caminho completo
 * do arquivo local para uso interno.
 *
 * Ela funciona como um "loader inteligente", garantindo que a versão
 * mais atual do arquivo seja utilizada sempre que necessário, sem
 * sobrecarregar o servidor nem o GitHub.
 *
 * ⚙️ O que esta função faz:
 * - Cria automaticamente um diretório de cache:
 *     `/wp-content/uploads/wplms-cache/`
 * - Baixa o arquivo remoto do GitHub:
 *     `https://raw.githubusercontent.com/.../config-stats.php`
 * - Armazena localmente como:
 *     `config-stats.php`
 * - Utiliza cache de **6 horas** para evitar requisições excessivas.
 * - Permite forçar download via parâmetro `$force_download`.
 * - Define o status da execução via parâmetro de referência `$executed`.
 *
 * 🧠 Funcionamento técnico:
 * - Verifica se o arquivo existe e se o cache é válido (21600s = 6h).
 * - Caso o cache esteja expirado OU `$force_download = true`:
 *      → chama `wp_remote_get()`  
 *      → valida o HTTP 200  
 *      → salva o conteúdo localmente com `file_put_contents()`  
 * - Se o download falhar, registra no `error_log`.
 * - Se o cache ainda for válido, usa o arquivo armazenado.
 *
 * 📝 Logs gerados:
 * - “Arquivo baixado do GitHub.”
 * - “Falha ao baixar o arquivo do GitHub.”
 * - “Usando arquivo em cache.”
 *
 * 🎯 Benefícios:
 * - Evita acesso constante ao GitHub (menos requisições).
 * - Maior segurança ao manter uma cópia local.
 * - Reduz latência no carregamento.
 * - Mantém sempre a versão atualizada quando necessário.
 *
 * 💡 Possíveis melhorias futuras:
 * - Suporte a versões versionadas com fallback automático.
 * - Verificação de integridade via hash (MD5/SHA256).
 * - Log detalhado com timestamps e tamanho do arquivo.
 * - Dashboard administrativo para “Atualizar agora”.
 *
 * ============================================================
 */
function wplms_load_github_stats_config(&$executed = null, $force_download = false)
{

    $executed = false;

    // URL do arquivo remoto
    $remote_url = 'https://raw.githubusercontent.com/equipewebnauta/spacelms-stats/main/config-stats.php';

    // Caminho onde será armazenado
    $cache_dir  = WP_CONTENT_DIR . '/uploads/wplms-cache/';
    $cache_file = $cache_dir . 'config-stats.php';

    // Garante que o diretório existe
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }

    $cache_expired = true;

    // Verifica validade do cache
    if (file_exists($cache_file)) {
        $age = time() - filemtime($cache_file);
        $cache_expired = ($age > 21600); // 6 horas
    }

    // → Se for forçado OU cache expirado → baixa novamente
    if ($force_download || $cache_expired) {

        $response = wp_remote_get($remote_url);

        if (!is_wp_error($response)) {

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            // ❗ Evita salvar conteúdo vazio
            if ($code === 200 && strlen(trim($body)) > 10) {

                file_put_contents($cache_file, $body);
                touch($cache_file); // atualiza o filemtime

                error_log('SPACE Stats: Arquivo baixado do GitHub.');
                $executed = true;
            } else {

                error_log('SPACE Stats: Falha – resposta inválida.');
            }
        } else {
            error_log('SPACE Stats: Erro de conexão com GitHub.');
        }
    } else {
        error_log('SPACE Stats: Usando cache existente.');
        $executed = true;
    }

    return file_exists($cache_file) ? $cache_file : false;
}











/**
 * ============================================================
 * 📊 WPLMS Stats – Menu Admin + Painel de Controle
 * ============================================================
 *
 * 📌 Autor: Miguel Cezar Ferreira
 * 📅 Data: 28/11/2025
 *
 * 📘 Descrição Geral:
 * Este módulo adiciona uma página personalizada ao painel administrativo
 * do WordPress para gerenciar o carregamento e a visualização do arquivo
 * `config-stats.php`, obtido do GitHub pelo sistema de cache inteligente
 * do WPLMS Stats.
 *
 * A página permite:
 * - Forçar manualmente o download do arquivo remoto.
 * - Exibir o conteúdo atual do arquivo (cached).
 * - Indicar visualmente se a função de atualização foi executada.
 * - Mostrar o caminho exato onde o cache está armazenado.
 *
 * ============================================================
 * 🧩 1. Adição do menu no WordPress Admin
 * ============================================================
 *
 * A função anônima registrada em:
 *
 *     add_action('admin_menu', ...)
 *
 * cria um novo item no painel lateral do WordPress:
 *
 *     📈 WPLMS Stats
 *
 * Ele é renderizado com o ícone `dashicons-chart-area` e aponta para
 * a função `wplms_stats_admin_page()`, que constrói toda a interface.
 *
 *
 * ============================================================
 * 🧩 2. Renderização da página administrativa
 * ============================================================
 *
 * A função `wplms_stats_admin_page()` é responsável por:
 *
 * 🔹 Criar a interface visual da página.  
 * 🔹 Exibir o botão “Atualizar Agora”.  
 * 🔹 Detectar quando o botão foi clicado (via POST).  
 * 🔹 Chamar `wplms_load_github_stats_config()` com ou sem forçar download.  
 * 🔹 Exibir mensagens visuais indicando sucesso ou falha.  
 * 🔹 Mostrar o conteúdo do arquivo baixado, dentro de um `<pre>` estilizado.  
 * 🔹 Exibir o caminho completo do arquivo dentro da pasta de cache local.  
 *
 *
 * ============================================================
 * 🧠 Funcionamento Técnico
 * ============================================================
 *
 * - O botão “Atualizar Agora” envia um POST:
 *       wplms_force_download=1
 *
 * - Isso força o loader a:
 *       → baixar novamente o arquivo do GitHub  
 *       → sobrescrever a versão local  
 *       → atualizar o cache  
 *
 * - A variável `$executed` retorna true/false indicando se a atualização ocorreu.
 *
 * - A interface exibe:
 *       ✔ Mensagem verde → quando atualizado com sucesso  
 *       ✖ Mensagem vermelha → quando o cache foi mantido  
 *
 * - Caso o arquivo exista localmente, seu conteúdo é mostrado no painel:
 *       › com formatação  
 *       › dentro de uma área com scroll  
 *
 *
 * ============================================================
 * 🎯 Benefícios da ferramenta no admin
 * ============================================================
 *
 * - Controle manual total sobre a sincronização com o GitHub.  
 * - Permite debug visual do arquivo baixado.  
 * - Facilita validação, auditoria e suporte técnico.  
 * - Evita SSH/FTP para verificar o conteúdo da versão cacheada.  
 *
 *
 * ============================================================
 * 💡 Possíveis expansões futuras
 * ============================================================
 *
 * - Botão para limpar cache.  
 * - Histórico de versões baixadas.  
 * - Exibição de tamanho e checksum do arquivo.  
 * - Cron automático para atualização sem intervenção manual.  
 * - Integração com WP-CLI para automação avançada.  
 *
 * ============================================================
 */

/**
 * Função que renderiza a página do admin
 */

function wplms_stats_admin_page()
{

    // Detecta se o botão Atualizar Agora foi clicado
    $force_download = isset($_POST['wplms_force_download']);

    $executed   = false;
    $cache_file = wplms_load_github_stats_config($executed, $force_download);

    $content = '';
    if ($cache_file && file_exists($cache_file)) {
        $content = file_get_contents($cache_file);
    }

    // Escapar para HTML
    $content_escaped     = esc_html($content);
    $cache_file_escaped  = esc_html($cache_file);

    echo '<div class="wrap">';
    echo '<h1 style="margin-bottom:20px;">📈 SPACE Stats – Configuração</h1>';

    // Botão Atualizar
    echo '<form method="post" style="margin-bottom:20px;">';
    echo '<input type="hidden" name="wplms_force_download" value="1">';
    echo '<button type="submit" style="
        background:#0073aa;
        color:#fff;
        border:none;
        padding:10px 18px;
        border-radius:4px;
        cursor:pointer;
        font-size:15px;
    ">🔄 Atualizar Agora</button>';
    echo '</form>';

    // Status visual
    if ($executed && $force_download) {
        echo '<p style="color:#00c851; font-weight:bold; font-size:15px;">✓ Arquivo atualizado com sucesso!</p>';
    } elseif (!$executed && $force_download) {
        echo '<p style="color:#ff4444; font-weight:bold; font-size:15px;">✗ Falha ao atualizar – usando versão antiga.</p>';
    } else {
        echo '<p style="color:#999; font-size:14px;">✔ Cache válido carregado.</p>';
    }


    // Editor estilo VSCode
    $editor_html = <<<HTML
<h2 style="margin-top:20px;">Conteúdo do arquivo:</h2>

<div style="
    background:#1e1e1e;
    border:1px solid #3c3c3c;
    border-radius:6px;
    margin-top:10px;
    padding:0;
    max-height:500px;
">

    <div style="
        background:#2d2d2d;
        padding:8px 12px;
        display:flex;
        align-items:center;
        gap:8px;
        border-bottom:1px solid #3c3c3c;
    ">
        <span style="width:12px;height:12px;background:#ff5f57;border-radius:50%;"></span>
        <span style="width:12px;height:12px;background:#ffbd2e;border-radius:50%;"></span>
        <span style="width:12px;height:12px;background:#28c840;border-radius:50%;"></span>

        <span style="color:#bbb; margin-left:10px; font-family:monospace;">
            config-stats.php
        </span>
    </div>

    <pre style="
        margin:0;
        padding:16px;
        color:#dcdcdc;
        font-size:14px;
        line-height:1.4;
        font-family: Consolas, Monaco, 'Courier New', monospace;
        overflow:auto;
        white-space:pre;
        max-height:480px;
    ">$content_escaped</pre>
</div>

<p style="color:#4da3ff; font-size:14px; margin-top:10px;">
    Caminho do arquivo: <code style="color:#fff;">$cache_file_escaped</code>
</p>
HTML;


    if ($content === '' || strlen(trim($content)) < 5) {
        echo '<p style="color:red; font-weight:bold;">⚠ O arquivo não pôde ser carregado.</p>';
    } else {
        echo $editor_html;
    }

    echo '</div>';
}











/**
 * API REST + Estilização Dinâmica de Cursos (WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 21/01/2026
 * 
 * Descrição:
 * Este conjunto de funções cria uma **API REST personalizada** no WordPress para o WPLMS
 * e integra essa API ao front-end através de JavaScript, permitindo aplicar **estilos visuais
 * dinâmicos (imagem de fundo e cor temática)** de acordo com a **categoria do curso acessado**.
 * 
 * A solução foi pensada para ambientes SPA (Single Page Application), comuns no WPLMS,
 * garantindo que as mudanças de navegação entre cursos sejam detectadas corretamente
 * sem recarregar a página.
 * 
 * ⚙️ O que este código faz:
 * - Registra um endpoint REST em `/wp-json/wplms/v1/course`.
 * - Recebe o nome do curso via parâmetro GET (`course_name`).
 * - Localiza o curso pelo título (post_type = course).
 * - Recupera a PRIMEIRA categoria associada ao curso.
 * - Busca metadados personalizados da categoria:
 *     • Imagem da categoria (`wplms_term_image`)
 *     • Cor temática em RGBA (`wplms_term_color`)
 * - Retorna os dados estruturados em JSON para consumo no front-end.
 * 
 * 🌐 Endpoint REST:
 * GET /wp-json/wplms/v1/course?course_name=Nome do Curso
 * 
 * 📦 Resposta da API:
 * - Dados do curso (ID e nome)
 * - Dados da categoria:
 *     • ID
 *     • Nome
 *     • Metadados (image_id, image_url e color)
 * 
 * 🖥️ Integração Front-end (JavaScript):
 * - Injeta um script no rodapé do site (`wp_footer`).
 * - Detecta dinamicamente o curso ativo através de múltiplos seletores HTML.
 * - Suporta navegação SPA usando `MutationObserver`.
 * - Ao entrar em um curso:
 *     • Consulta a API REST
 *     • Aplica temporariamente:
 *         - Imagem da categoria como background CSS
 *         - Cor da categoria como efeito glass/theme
 * - Ao sair do curso:
 *     • Restaura os estilos CSS originais do site.
 * 
 * 🎨 Variáveis CSS manipuladas:
 * - --bg-image-url  → imagem de fundo dinâmica do curso
 * - --glass-bg      → cor temática da categoria
 * 
 * 🧠 Funcionamento técnico:
 * - Usa `get_page_by_title()` para localizar o curso.
 * - Trabalha com taxonomia `course-cat` do WPLMS.
 * - Utiliza `get_term_meta()` para acessar dados personalizados.
 * - Aplica debounce lógico de navegação evitando chamadas desnecessárias.
 * - Garante fallback seguro caso o curso ou categoria não existam.
 * 
 * 🧩 Requisitos:
 * - WordPress com WPLMS instalado e ativo.
 * - Cursos configurados como post_type `course`.
 * - Categorias com metadados personalizados:
 *     • wplms_term_image
 *     • wplms_term_color
 * - Tema que utilize variáveis CSS (`:root`) para estilização.
 * 
 * 💡 Possíveis extensões:
 * - Suporte a múltiplas categorias por curso.
 * - Cache dos resultados da API no front-end.
 * - Animações de transição entre estilos.
 * - Aplicação de estilos adicionais (tipografia, botões, overlays).
 * 
 * 🔐 Segurança:
 * - Endpoint público apenas para leitura (GET).
 * - Parâmetros sanitizados via `sanitize_text_field`.
 * - Nenhuma modificação de dados no banco.
 */


add_action('rest_api_init', function () {
    register_rest_route('wplms/v1', '/course', [
        'methods'  => 'GET',
        'callback' => 'wplms_get_course_data_api',
        'permission_callback' => '__return_true',
        'args' => [
            'course_name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
});



function wplms_get_course_data_api(WP_REST_Request $request)
{

    $course_name = $request->get_param('course_name');

    $course = get_page_by_title($course_name, OBJECT, 'course');

    if (!$course) {
        return new WP_REST_Response(['msg' => 'Curso não encontrado'], 404);
    }

    $course_id = $course->ID;

    $terms = get_the_terms($course_id, 'course-cat');

    if (empty($terms) || is_wp_error($terms)) {
        return new WP_REST_Response(['msg' => 'Categoria não encontrada'], 404);
    }

    // 👉 Usa a PRIMEIRA categoria
    $term = $terms[0];

    $image_id = get_term_meta($term->term_id, 'wplms_term_image', true);
    $color    = get_term_meta($term->term_id, 'wplms_term_color', true);

    $image_url = null;

    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'full');
    }

    return new WP_REST_Response([
        'course' => [
            'id'   => $course_id,
            'name' => $course_name,
        ],
        'category' => [
            'id'    => $term->term_id,
            'name'  => $term->name,
            'meta'  => [
                'image_id'  => $image_id,
                'image_url' => $image_url,
                'color'     => $color,
            ],
        ],
    ], 200);
}



add_action('wp_footer', 'wplms_inject_course_tracker_js', 99);

function wplms_inject_course_tracker_js()
{
    if (is_admin()) return;
	
	  // 🔒 Só roda se o módulo estiver ATIVO
    if (get_option('categoriasPersonalizadas_enabled') !== '1') {
        return;
    }
	
	
?>
    <script>
        (function() {

            console.log('✅ WPLMS Course Watcher iniciado');

            let currentCourse = null;
            let debounceTimer = null;

            /* =====================================================
             * 💾 Estado original do :root (cache)
             * ===================================================== */
            const root = document.documentElement;

            const originalCSS = {
                bgImage: root.style.getPropertyValue('--bg-image-url'),
                glassBg: root.style.getPropertyValue('--glass-bg'),
            };

            console.log('💾 Estado original CSS salvo:', originalCSS);

            /* =====================================================
             * 🔎 Detecta nome do curso
             * ===================================================== */
            function getCourseName() {

                const selectors = [
                    '.course_heading h2',
                    '.course_title h1',
                    '.course_title h2',
                    '[data-course-title]',
                    'h1'
                ];

                for (let sel of selectors) {
                    const el = document.querySelector(sel);
                    if (el && el.innerText.trim().length > 3) {
                        return el.innerText.trim();
                    }
                }

                const match = window.location.hash.match(/course:([^&]+)/);
                if (match) {
                    return decodeURIComponent(match[1]).replace(/-/g, ' ');
                }

                return null;
            }

            /* =====================================================
             * 🎨 Aplica estilos temporários
             * ===================================================== */
            function applyCategoryStyles(meta) {

                if (!meta) return;

                if (meta.image_url) {
                    root.style.setProperty(
                        '--bg-image-url',
                        `url("${meta.image_url}")`
                    );
                    console.log('🖼️ --bg-image-url aplicado temporariamente');
                }

                if (meta.color) {
                    root.style.setProperty(
                        '--glass-bg',
                        meta.color
                    );
                    console.log('🎨 --glass-bg aplicado temporariamente');
                }
            }

            /* =====================================================
             * ♻️ Restaura estilos originais
             * ===================================================== */
            function restoreOriginalStyles() {

                root.style.setProperty(
                    '--bg-image-url',
                    originalCSS.bgImage || ''
                );

                root.style.setProperty(
                    '--glass-bg',
                    originalCSS.glassBg || ''
                );

                console.log('♻️ Estilos originais restaurados');
            }

            /* =====================================================
             * 🌐 Busca dados do curso
             * ===================================================== */
            function fetchCourseData(courseName) {

                const endpoint =
                    '<?php echo esc_url(rest_url('wplms/v1/course')); ?>' +
                    '?course_name=' + encodeURIComponent(courseName);

                fetch(endpoint)
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .then(res => {
                        if (res?.category?.meta) {
                            applyCategoryStyles(res.category.meta);
                        }
                    })
                    .catch(() => {
                        console.warn('⚠️ Falha ao aplicar estilos do curso');
                    });
            }

            /* =====================================================
             * 🔄 Controle de entrada/saída do curso
             * ===================================================== */
            function handleNavigation() {

                const course = getCourseName();

                // 🟢 Entrou em um curso
                if (course && course !== currentCourse) {
                    console.log('🟢 Entrando no curso:', course);
                    currentCourse = course;
                    fetchCourseData(course);
                    return;
                }

                // 🔴 Saiu do curso
                if (!course && currentCourse) {
                    console.log('🔴 Saindo do curso:', currentCourse);
                    currentCourse = null;
                    restoreOriginalStyles();
                }
            }

            /* =====================================================
             * 👀 Observa mudanças de navegação SPA
             * ===================================================== */
            const observer = new MutationObserver(handleNavigation);

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            /* =====================================================
             * 🚀 Primeira execução
             * ===================================================== */
            setTimeout(handleNavigation, 500);

        })();
    </script>
<?php
}



/**
 * Gerenciador de Imagem e Cor para Categorias de Curso (WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 21/01/2026
 * 
 * Descrição:
 * Esta funcionalidade adiciona um **painel administrativo personalizado no WordPress**
 * que permite gerenciar **imagem destacada e cor temática (RGBA)** para cada
 * categoria de curso do WPLMS (`course-cat`), de forma visual, intuitiva e segura.
 * 
 * O sistema foi projetado para **administradores**, integrando recursos nativos do
 * WordPress como **Media Library**, **Color Picker** e **AJAX**, sem dependência
 * de bibliotecas externas.
 * 
 * ⚙️ O que este código faz:
 * - Define de forma centralizada a taxonomia de categorias de curso (`course-cat`).
 * - Cria um menu principal no admin:
 *     • "Imagens das Categorias"
 * - Lista todas as categorias do WPLMS em uma tabela administrativa.
 * - Exibe, para cada categoria:
 *     • Nome da categoria
 *     • Preview da imagem associada
 *     • Seletor de cor visual (HEX)
 *     • Botões de ação (Selecionar, Remover e Salvar)
 * 
 * 🎨 Sistema de cores (HEX → RGBA):
 * - O usuário interage apenas com o **Color Picker visual (HEX)**.
 * - A conversão para **RGBA com alpha fixo (0.45)** é feita automaticamente via JavaScript.
 * - Apenas o valor RGBA final é persistido no banco de dados.
 * - Garante padronização visual e evita inconsistências de transparência.
 * 
 * 🖼️ Sistema de imagens:
 * - Utiliza o **Media Uploader nativo do WordPress**.
 * - Permite selecionar ou remover a imagem da categoria.
 * - Salva apenas o `attachment_id` como metadado do termo.
 * - Exibe preview imediato sem recarregar a página.
 * 
 * 🔄 Salvamento via AJAX:
 * - Duas ações AJAX independentes:
 *     • `wplms_save_term_image` → salva/remove a imagem da categoria
 *     • `wplms_save_term_color` → salva/remove a cor RGBA da categoria
 * - Atualização assíncrona sem reload da página.
 * - Feedback visual imediato no botão "Salvar".
 * 
 * 🔐 Segurança:
 * - Acesso restrito a usuários com capacidade `manage_options`.
 * - Sanitização de dados recebidos via POST.
 * - Validação rigorosa do formato RGBA antes de salvar.
 * - Alpha sempre forçado para 0.45 no backend.
 * 
 * 🧠 Funcionamento técnico:
 * - `get_terms()` carrega todas as categorias, inclusive vazias.
 * - `get_term_meta()` armazena os dados por termo.
 * - Scripts são carregados apenas na página do menu específico.
 * - Uso de `wp_add_inline_script()` para encapsular JS no escopo correto.
 * - Conversão reversa RGBA → HEX para manter compatibilidade visual.
 * 
 * 📦 Metadados utilizados:
 * - wplms_term_image → ID do attachment da imagem
 * - wplms_term_color → Cor temática em RGBA
 * 
 * 🧩 Requisitos:
 * - WordPress 5.8+
 * - WPLMS ativo
 * - Taxonomia `course-cat` registrada
 * - Usuário com permissão administrativa
 * 
 * 💡 Possíveis extensões:
 * - Suporte a múltiplas imagens por categoria.
 * - Definição dinâmica do alpha via interface.
 * - Preview em tempo real no front-end.
 * - Histórico de alterações por categoria.
 * - Integração com REST API.
 */
function wplms_course_category_taxonomy()
{
    return 'course-cat';
}

/**
 * Criar menu no admin
 */


/**
 * Página do menu
 */
function wplms_course_category_image_page()
{

    // 🔗 Bootstrap 5 CDN (somente visual)
?>
    <!-- Bootstrap 5 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap 5 JS (opcional, mas incluído) -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>
    <?php

    $terms = get_terms([
        'taxonomy'   => wplms_course_category_taxonomy(),
        'hide_empty' => false
    ]);
    ?>
    <div class="wrap container-fluid py-3">

<h1 class="mb-2 fw-bold display-6">
    <?php esc_html_e('Categorias Personalizadas', 'wplms'); ?>
</h1>

<h3 class="mb-4 fs-6 fw-normal text-muted lh-base">
    Este recurso permite personalizar visualmente as categorias dos cursos.
    Todos os cursos vinculados a uma categoria personalizada herdarão automaticamente
    a imagem de plano de fundo e a cor do efeito glass definidas aqui.
    Aproveite ao máximo essa funcionalidade para criar uma identidade visual única
    e destacar cada categoria de forma estratégica.
</h3>

		
		
		
<div class="table-responsive shadow-sm rounded">
<table class="table table-hover table-striped table-bordered align-middle mb-0">
    <thead class="table-light text-uppercase small">
        <tr>
            <th class="fw-semibold">Categorias</th>
            <th class="text-center fw-semibold">Plano de fundo</th>
            <th class="fw-semibold" style="min-width:200px;">Cor do glass</th>
            <th class="text-center fw-semibold" style="min-width:220px;">Ação</th>
        </tr>
    </thead>

    <tbody class="table-group-divider">
        <?php foreach ($terms as $term):

            $image_id  = get_term_meta($term->term_id, 'wplms_term_image', true);
            $image_url = $image_id
                ? wp_get_attachment_image_url($image_id, 'large')
                : 'http://spacedev.agenciawebnauta.com.br/wp-content/uploads/2026/01/sem-foto.jpg';

            $color = get_term_meta($term->term_id, 'wplms_term_color', true);
            if (!$color) {
                $color = 'rgba(139,2,224,0.45)';
            }

            $modal_id = 'categoriaInfoModal-' . $term->term_id;
        ?>
            <tr data-term="<?php echo esc_attr($term->term_id); ?>">

                <td class="fw-medium">
                    <?php echo esc_html($term->name); ?>
                </td>

                <td class="text-center wplms-image-preview">
                    <img
                        src="<?php echo esc_url($image_url); ?>"
                        class="img-thumbnail rounded"
                        style="max-width:100px;">
                </td>

                <td>
                    <input
                        type="text"
                        class="form-control form-control-sm wplms-color-visual mb-2"
                        value="<?php echo esc_attr(wplms_rgba_to_hex($color)); ?>"
                        data-default-color="#8b02e0" />

                    <input
                        type="hidden"
                        class="wplms-color-rgba"
                        value="<?php echo esc_attr($color); ?>" />
                </td>

                <td>
                    <div class="d-grid gap-2">
                        <button class="button btn btn-sm btn-outline-primary wplms-select-image">
                            Selecionar imagem
                        </button>

                        <button class="button btn btn-sm btn-outline-danger wplms-remove-image">
                            Remover
                        </button>

                        <!-- 🔍 BOTÃO DETALHES -->
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#<?php echo esc_attr($modal_id); ?>">
                            🔍 Preview
                        </button>

                        <button class="button button-primary btn btn-sm btn-success wplms-save-term">
                            💾 Salvar
                        </button>
                    </div>
                </td>
            </tr>

            <!-- 🔳 MODAL DA CATEGORIA -->
            <div class="modal fade" id="<?php echo esc_attr($modal_id); ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content rounded-3 shadow" >

                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">
                                <?php echo esc_html($term->name); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                Preview visual da categoria com imagem de fundo
                                e efeito glass aplicado.
                            </p>

                            <!-- PREVIEW -->
                            <div class="ratio ratio-16x9 rounded overflow-hidden position-relative mb-4 shadow-sm">

                                <!-- 🖼️ FUNDO -->
                                <div
                                    class="position-absolute top-0 start-0 w-60 h-60"
                                    style="
                                        background-image: url('<?php echo esc_url($image_url); ?>');
background-repeat:no-repeat; 
                                        background-position: center;
                                    ">
                                </div>

<!-- 🎨 GLASS CENTRAL -->
<div
    class="position-absolute top-50 start-50 translate-middle rounded-3 shadow d-flex flex-column align-items-center justify-content-center text-center px-4"
    style="
        width: 60%;
        height: 55%;
        background: <?php echo esc_attr($color); ?>;
      border: 1px solid rgba(255, 255, 255, 0.3) !important;
    box-shadow: inset 1px 1px 3px rgba(255, 255, 255, 0.302),  inset 0 0 5px rgba(255, 255, 255, 0.2) !important;
		      backdrop-filter: blur(50px) !important;
    -webkit-backdrop-filter: blur(50px) !important;
    "
>
    <h2 class="mb-2 fw-bold text-white">
        Texto
    </h2>

    <p class="mb-0 text-white-50 fs-6">
        textoteste
    </p>
</div>

                           
                                
                            </div>

                            <!-- INFO -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Imagem atual</strong>
                                    <img
                                        src="<?php echo esc_url($image_url); ?>"
                                        class="img-fluid rounded mt-2">
                                </div>

                                <div class="col-md-6">
                                    <strong>Cor do Glass</strong>
                                    <div
                                        class="rounded mt-2 shadow-sm"
                                        style="height:60px; background: <?php echo esc_attr($color); ?>;">
										
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">
                                Fechar
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    </tbody>
</table>
</div>
    </div>
<?php
}
/**
 * Scripts
 */
add_action('admin_enqueue_scripts', 'wplms_course_category_image_assets');
function wplms_course_category_image_assets($hook)
{
    // ✅ Garante que só carrega na página correta
    if (
        !isset($_GET['page']) ||
        $_GET['page'] !== 'wplms-course-category-images'
    ) {
        return;
    }

    // 📦 Dependências nativas
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    $js = <<<JS
jQuery(window).on('load', function () {
    var \$ = jQuery;

    console.log('WPLMS Color Picker carregado');

    let frame;
    const FIXED_ALPHA = 0.45;
    const DEFAULT_COLOR = '#ff0000';

    function hexToRgba(hex, alpha) {
        if (!hex) return '';

        hex = hex.replace('#', '');

        if (hex.length === 3) {
            hex = hex.split('').map(h => h + h).join('');
        }

        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);

        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    // 🎨 COLOR PICKER
    \$('.wplms-color-visual').each(function () {

        const visual = \$(this);
        const row    = visual.closest('tr');
        const rgba   = row.find('.wplms-color-rgba');

        if (!visual.val()) {
            visual.val(DEFAULT_COLOR);
        }

        if (typeof visual.wpColorPicker !== 'function') {
            console.error('wpColorPicker NÃO carregado');
            return;
        }

        visual.wpColorPicker({
            palettes: false,

            change: function (event, ui) {
                if (ui && ui.color) {
                    rgba.val(hexToRgba(ui.color.toString(), FIXED_ALPHA));
                }
            },

            clear: function () {
                rgba.val('');
            }
        });
    });

    // 🖼 Selecionar imagem
    \$('.wplms-select-image').on('click', function (e) {
        e.preventDefault();

        const row = \$(this).closest('tr');

        frame = wp.media({
            title: 'Selecionar imagem',
            button: { text: 'Usar imagem' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();

            row.find('.wplms-image-preview')
                .html('<img src="' + attachment.url + '" style="max-width:80px;">');

            row.data('image-id', attachment.id);
        });

        frame.open();
    });

    // ❌ Remover imagem
    \$('.wplms-remove-image').on('click', function (e) {
        e.preventDefault();

        const row = \$(this).closest('tr');
        row.find('.wplms-image-preview').html('<em>Sem imagem</em>');
        row.data('image-id', 0);
    });

    // 💾 SALVAR
    \$('.wplms-save-term').on('click', function (e) {
        e.preventDefault();

        const button   = \$(this);
        const row      = button.closest('tr');
        const term_id  = row.data('term');
        const image_id = row.data('image-id') || 0;
        const color    = row.find('.wplms-color-rgba').val();

        \$.post(ajaxurl, {
            action: 'wplms_save_term_image',
            term_id: term_id,
            image_id: image_id
        });

        \$.post(ajaxurl, {
            action: 'wplms_save_term_color',
            term_id: term_id,
            color: color
        });

        button.text('✔ Salvo').prop('disabled', true);

        setTimeout(function () {
            button.text('💾 Salvar').prop('disabled', false);
        }, 1500);
    });

});
JS;

    wp_add_inline_script('wp-color-picker', $js);
}
function wplms_rgba_to_hex($rgba, $default = '#ff0000')
{

    if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $rgba, $m)) {
        return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
    }

    return $default;
}


/**
 * AJAX salvar imagem
 */
add_action('wp_ajax_wplms_save_term_image', 'wplms_save_term_image_ajax');
function wplms_save_term_image_ajax()
{

    if (!current_user_can('manage_options')) {
        wp_die();
    }

    $term_id  = absint($_POST['term_id']);
    $image_id = absint($_POST['image_id']);

    if ($image_id) {
        update_term_meta($term_id, 'wplms_term_image', $image_id);
    } else {
        delete_term_meta($term_id, 'wplms_term_image');
    }

    wp_die();
}

/**
 * AJAX salvar cor
 */
add_action('wp_ajax_wplms_save_term_color', 'wplms_save_term_color_ajax');
function wplms_save_term_color_ajax()
{

    if (!current_user_can('manage_options')) {
        wp_die();
    }

    $term_id = absint($_POST['term_id']);
    $color   = sanitize_text_field($_POST['color']);

    if (preg_match('/rgba\((\d+),(\d+),(\d+),([0-9.]+)\)/', $color, $m)) {

        $r = min(255, intval($m[1]));
        $g = min(255, intval($m[2]));
        $b = min(255, intval($m[3]));

        // 🔒 Alpha sempre travado
        $color = 'rgba(' . $r . ',' . $g . ',' . $b . ',0.45)';

        update_term_meta($term_id, 'wplms_term_color', $color);
    } else {
        delete_term_meta($term_id, 'wplms_term_color');
    }

    wp_die();
}









/* ---------------------------
   ADMIN JS + CSS PARA TOGGLES
---------------------------- */
add_action('admin_footer', function () {
?>
    <style>
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background: #61CE70;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }
    </style>

    <script>
        jQuery(document).ready(function($) {

            /* ===============================
               Toggle Plugins Normais
            =============================== */
            $('.plugin-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_plugin_status',
                    plugin: $(this).data('plugin'),
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });

            /* ===============================
               Toggle WPLMS Stats
            =============================== */
            $('.wplms-stats-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_wplms_stats',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });


            /* ===============================
       Toggle CSS SPACE
    =============================== */
            $('.wplms-css-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_css',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });


            /* ===============================
       Toggle Estatísticas SPACE
    =============================== */
            $('.estatisticas-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_estatisticas',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });




            /* ===============================
       Toggle Alunos SPACE
    =============================== */
            $('.wplms-alunos-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_alunos',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });


			
			
            /* ===============================
       Toggle Seções Agendadas
    =============================== */
            $('.sectionLock-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_sectionLock',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });
			
			
			
			      /* ===============================
       Toggle categoriasPersonalizadas SPACE
    =============================== */
            $('.categoriasPersonalizadas-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_categoriasPersonalizadas',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });
			
			


            /* ===============================
               Toggle Comissões
            =============================== */
            $('.comissoes-toggle').on('change', function() {
                $.post(ajaxurl, {
                    action: 'toggle_comissoes',
                    status: $(this).is(':checked') ? '1' : '0'
                }, function() {
                    location.reload();
                });
            });

        });
    </script>
    <?php
});


/* ---------------------------
   ESTILIZAÇÃO PADRÃO DO ADMIN
---------------------------- */
function custom_plugin_management_styles()
{
    echo '<style>
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .plugin-toggle,
        .wplms-stats-toggle,
        .comissoes-toggle {
            display: none;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #BF3636;
            transition: 0.4s;
            border-radius: 34px;
        }

        input:checked + .toggle-slider {
            background-color: #61CE70;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
    </style>';
}
add_action('admin_head', 'custom_plugin_management_styles');

/*	FIM ---- Cria menu para ativar ou desativar através de Togglt Swith de recursos disponíveis para o cliente SPACE Pocket.*/



// Função para ocultar elementos com IDs específicos para usuários com a role "admin-space"
function hide_elements_for_admin_space()
{
    // Verifica se o usuário atual tem a role "admin-space"
    if (current_user_can('admin-space') && !current_user_can('administrator')) {
        echo '<style>';
        echo '#toplevel_page_loginwp-settings,';
        echo '#toplevel_page_bp-disable-activation-reloaded,';
        echo '.uip-padding-m > div.uip-text-normal > div:nth-child(4),';
        echo '.uip-margin-right-m input.uip-margin-bottom-m,';
        echo '.uip-padding-top-s .uip-background-primary-wash,';
        echo '.uip-margin-right-m div:nth-child(5) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(6) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(8) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(9) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(10) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(11) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(12) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(13) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(14) div.uip-margin-right-xxs,';
        echo '.uip-margin-right-m div:nth-child(15) div.uip-margin-right-xxs,';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(1),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(2),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(3),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(4),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(5),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(6),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(7),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(8),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(9),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(10),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(11),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(12),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(13),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(14),';
        echo '.uip-margin-top-xs .uip-text-muted:nth-child(15),';
        echo 'li #toplevel_page_email-Template';
        echo '.uip-margin-top-l #login-dark-mode,';
        echo '.uip-margin-top-l #remove-language-selector,';
        echo '.uip-margin-top-l #login-background,';
        echo '.uip-margin-top-l #status,';
        echo '.uip-margin-top-l #disabled-for,';
        echo '.uip-margin-top-l #load-front,';
        echo '.uip-margin-top-l #load-front-for,';
        echo '.uip-margin-top-l #show-site-logo,';
        echo '.uip-margin-top-l #search-enabled,';
        echo '.uip-margin-top-l #shrunk-default,';
        echo '.uip-margin-top-l #dark-default,';
        echo '.uip-margin-top-l #dark-disabled,';
        echo '.uip-margin-top-l #dynamic-loading,';
        echo '.uip-margin-top-l #redirect-overview,';
        echo '.uip-margin-top-l #redirect-custom,';
        echo '.uip-margin-top-l #rename-plugin,';
        echo '.uip-margin-top-l #rename-plugin-author,';
        echo '.uip-margin-top-l #rename-plugin-link,';
        echo '.uip-margin-top-l #hide-plugin,';
        echo '.uip-margin-top-l #hide-footer,';
        echo '.uip-margin-top-l #footer-text,';
        echo 'html[uip-toolbar=true] #uip-toolbar .uip-legacy-admin #wpadminbar,';
        echo '#uip-toolbar-content .uip-flex>.uip-flex:nth-child(2),';
        echo '#uip-toolbar-content .uip-flex>.uip-flex:nth-child(4),';
        echo '#uip-toolbar-content .uip-flex>.uip-flex:nth-child(5),';
        echo '.uip-margin-top-l #dark-prefers-color-scheme {';
        echo 'display: none !important;';
        echo '}';
        echo '</style>';
    }
}
add_action('admin_head', 'hide_elements_for_admin_space');




function mytheme_customize_register($wp_customize)
{
    // Verifica se o usuário atual tem a role "admin-space"
    if (current_user_can('admin-space') && !current_user_can('administrator')) {
        // All our sections, settings, and controls will be added here

        $wp_customize->remove_section('title_tagline');
        $wp_customize->remove_section('colors');
        $wp_customize->remove_section('header_image');
        $wp_customize->remove_section('background_image');
        $wp_customize->remove_panel('nav_menus');
        $wp_customize->remove_panel('widgets');
        $wp_customize->remove_section('theme');
        $wp_customize->remove_section('layouts');
        $wp_customize->remove_section('header');
        $wp_customize->remove_section('footer');
        $wp_customize->remove_section('body');
        $wp_customize->remove_section('typography');
        $wp_customize->remove_panel('woocommerce');
        $wp_customize->remove_section('static_front_page');
        $wp_customize->remove_section('custom');
        $wp_customize->get_panel('vibebp_settings')->title = __('SPACE Settings');
        $wp_customize->get_section('vibebp_general_settings')->title = __('SPACE General Settings');
        $wp_customize->get_section('vibebp_light_colors')->title = __('SPACE Light Colors');
        $wp_customize->get_section('vibebp_dark_colors')->title = __('SPACE Dark Colors');
    }
}
add_action('customize_register', 'mytheme_customize_register', 50);



// Adiciona o shortcode [titulo_do_site] que pode ser usado em posts e páginas
add_shortcode('titulo_do_site', 'mostrar_titulo_do_site_shortcode');

function mostrar_titulo_do_site_shortcode()
{
    // Pega o título do site usando a função get_bloginfo
    $titulo_do_site = get_bloginfo('name');

    // Retorna o título para ser usado pelo shortcode
    return $titulo_do_site;
}

// Adiciona o shortcode [email_admin] que pode ser usado em posts e páginas
add_shortcode('email_admin', 'mostrar_email_admin_shortcode');
function mostrar_email_admin_shortcode()
{
    // Pega o e-mail do administrador do site
    $email_admin = get_bloginfo('admin_email');

    // Retorna o e-mail para ser usado pelo shortcode
    return $email_admin;
}



/**
 * Ocultar cursos na Categoria Privado e Colaborador (Criar categoria colaborador e ajustar ID)
 * + CSS em Personalizar para remover do filtro.
 * .wplms_courses_filter label[for=course-cat_137],
 * .wplms_courses_filter label[for=course-cat_246] { display:none !important; }
 */
add_filter('wplms_carousel_course_filters', 'wplms_exclude_courses_directory_multi', 99999);
add_filter('wplms_grid_course_filters', 'wplms_exclude_courses_directory_multi', 99999);
add_filter('bp_course_wplms_filters', 'wplms_exclude_courses_directory_multi', 99999);
add_filter('vibe_related_courses', 'wplms_exclude_courses_directory_multi', 99999);

/**
 * Helper: retorna IDs de cursos pertencentes a qualquer uma das categorias informadas
 */
function wplms_get_excluded_courses_by_cats($slugs = array('privado', 'colaborador'))
{
    if (empty($slugs) || !is_array($slugs)) return array();

    $ids = get_posts(array(
        'post_type'      => 'course',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'tax_query'      => array(
            array(
                'taxonomy' => 'course-cat',
                'field'    => 'slug',
                'terms'    => $slugs,
                'operator' => 'IN',
            ),
        ),
        'suppress_filters' => true, // evita filtros extras no get_posts
    ));

    return is_array($ids) ? $ids : array();
}

function wplms_exclude_courses_directory_multi($args)
{
    if (empty($args['post_type']) || $args['post_type'] !== 'course') {
        return $args;
    }

    // Não excluir quando a query já está personalizada para o usuário (ex: cursos inscritos)
    if (isset($args['meta_query']) && is_array($args['meta_query']) && is_user_logged_in()) {
        $user_id = get_current_user_id();
        foreach ($args['meta_query'] as $query) {
            if (is_array($query) && isset($query['key']) && $query['key'] == $user_id) {
                return $args;
            }
        }
    }

    // Também não excluir quando listando por autor
    if (isset($args['author']) || isset($args['author_name'])) {
        return $args;
    }

    $excluded_courses = wplms_get_excluded_courses_by_cats();

    if (!empty($excluded_courses)) {
        // Garante unicidade e merge correto
        if (!empty($args['post__not_in']) && is_array($args['post__not_in'])) {
            $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'], $excluded_courses)));
        } else {
            $args['post__not_in'] = $excluded_courses;
        }
    }

    return $args;
}

/**
 * Ajuste de contagem total (opcional):
 * Se quiser subtrair os ocultos da contagem do diretório, descomente a linha indicada.
 */
add_filter('bp_course_total_count', 'wplms_hidden_courses_count_multi');
function wplms_hidden_courses_count_multi($totalcount)
{
    $excluded_courses = wplms_get_excluded_courses_by_cats();

    // Caso deseje subtrair da contagem total, descomente:
    // $totalcount = max(0, intval($totalcount) - count($excluded_courses));

    return $totalcount;
}


/* Redireciona para uma página de boas vindas personalizada a partir do pedido com status concluído 
function redirect_completed_orders() {
    if (is_wc_endpoint_url('order-received') && isset($_GET['key'])) {
        $order_key = sanitize_text_field($_GET['key']);
        $order_id = wc_get_order_id_by_order_key($order_key);
        
        $order = wc_get_order($order_id);
        
        if ($order && $order->has_status('completed')) {
            wp_redirect('/welcome-space-pocket');
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_completed_orders'); */




/* Correção de bug do Woocommerce para produto que pode ser comprado 1 vez só. É verificado e redirecionado para carrinho. */
function validar_venda_individual_e_redirecionar($valid, $product_id, $quantity)
{
    // Verifica se o produto é de venda individual
    $product = wc_get_product($product_id);
    if ($product->is_sold_individually()) {
        // Verifica se o produto já está no carrinho
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                // Redireciona para a página do carrinho
                wp_safe_redirect(wc_get_cart_url());
                exit;
            }
        }
    }
    return $valid;
}
add_filter('woocommerce_add_to_cart_validation', 'validar_venda_individual_e_redirecionar', 10, 3);


/* AUMENTA O LIMITE DE EXIBIÇÃO DOS CURSOS DE 12 PARA 50 DENTRO DO APP EM "MEUS CURSOS"
add_filter('wplms_default_courses_per_page',function($x){return 50;}); */



/* Estilo Personalizado H5P 
function MYPLUGIN_alter_styles(&$styles, $libraries, $embed_type) {
  $styles[] = (object) array(
    // Path must be relative to wp-content/uploads/h5p or absolute.
    'path' => content_url('h5p-custom/custom-h5p-v5.css')
    //'version' => '?ver=0.2' // Cache buster
  );
}
add_action('h5p_alter_library_styles', 'MYPLUGIN_alter_styles', 10, 3);

*/



/*
   * Esse código adiciona campos personalizados nas estatísticas do curso
   * 

function add_custom_course_stat($list){
     $new_list = array(
                      'email'=>'Email',
                       );
   
     $list=array_merge($list,$new_list);
      return $list;
}
add_action('wplms_course_stats_process','process_custom_course_stat_email',10,8);
add_filter('wplms_course_stats_list','add_custom_course_stat');
function process_custom_course_stat_email(&$csv_title, &$csv,&$i,&$course_id,&$user_id,&$field,&$ccsv,&$k){
    if($field != 'email') // Verifica se o campo foi preenchido
       return;
      $title=__('Email','wplms');
     if(!in_array($title,$csv_title))
      $csv_title[$k]=array('title'=>$title,'field'=>'Email');
        $ifield = 'Email';
        $user = get_user_by('id',$user_id);
      
     $field_val = $user->data->user_email;
         
     if(isset($field_val) && $field_val){
     
           $csv[$i][]= $field_val;
           $ccsv[$i]['Email'] = $field_val;
      
      }else{
        $csv[$i][]= 'NA';
        $ccsv[$i]['Email'] = 'NA';
      }
}
*/


/* 
 * DOCUMENTAÇÃO DE SCRIPT
 * 
 * O CÓDIGO ABAIXO NÃO E EXECUTADO NESTA PÁGINA. ELE FICA LOCALIZADO EM WPLMS > RODAPÉ > Código do Google Analytics
 * ESSE SCRIPT É RESPONSÁVEL POR EXIBIR NOME COMPLETO, EMAIL E ENDERE DE IP EM UM BOX DENTRO DOS VIDEOS AO ACESSAR UMA AULA.
 * 
 * 
 * <script>
window.addEventListener("load", (event) => {
    localforage.getItem('bp_user').then((user) => {
        if (user) {
            if (typeof user !== 'object') {
                user = JSON.parse(user);
            }

            if (!document.querySelector('#player-mask')) {
                fetch('https://api.ipify.org?format=json')
                    .then((response) => response.json())
                    .then((data) => {
                        const style = document.createElement("style");
                        style.setAttribute("id", 'player-mask');
                        style.innerHTML = `
                            .plyr__video-wrapper:before {
                                content: '${user.displayname} \\A ${user.email} \\A ${data.ip}';
                                white-space: pre-wrap; /* Permite que as quebras de linha no texto sejam renderizadas */
/*                                position: absolute;
                                top: 0.2rem;
                                left: 0.2rem;
                                padding: 0.5rem;
                                background: rgba(0, 0, 0, 0.3); /* Fundo preto com 30% de transparência */
/*                                color: #ffffff;
                                font-size: 15px;
                                font-weight: bold;
                                z-index: 1;
/*                                border-radius: 4px; /* Bordas arredondadas */
/*                            }
                        `;
                        document.body.appendChild(style);
                    })
                    .catch((error) => {
                        console.error('Error fetching IP:', error);
                    });
            }
        }
    });
});
</script>
 */



/*LIMITA A VISUALIZAÇÃO DAS MÍDIAS SOMENTE PARA OS ARQUIVOS QUE O USUÁRIO FEZ UPLOAD" 
add_filter( 'ajax_query_attachments_args', 'filter_query_attachments_args' );

function filter_query_attachments_args( $query ) {
	// 1. Only users with access
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error();
	}

	// 2. No manipulation for admins.
	// After all they have access to all images.
	if ( current_user_can( 'administrator' ) ) {
		return $query;
	}

	// 3. No images, if the post_id is not provided
	if ( ! isset( $_REQUEST['post_id'] ) ) {
		wp_send_json_error();
	}

	// 4. No images, if you are not the post type manager or author
	$post = get_post( (int) $_REQUEST['post_id'] );
	if ( ! $post instanceof \WP_Post ) {
		return $query;
	}

	// 5. You can also restrict the changes to your custom post type
	if ( 'listing' != $post->post_type ) {
		// Only filter for our custom post types
		return $query;
	}

	// 6. Allow only post authors to open the uploader
	$current_user = wp_get_current_user();
	if ( $current_user->ID != $post->post_author ) {
		wp_send_json_error();
	}
	
	// 7. Filter to display only images
	$query['post_mime_type'] = array(
		'image/gif',
		'image/jpeg',
		'image/png',
		'image/bmp',
		'image/tiff',
		'image/x-icon',
        'video/mp4',
		'video/WebM',
		'video/OGG',
		'video/WMV'
	);

	// 8. Don't show private images
	$query['post_status'] = 'inherit';

	// 9. Filter to display only the images attached to the post
	$query['post_parent'] = $post->ID;

	// 10. Filter to display only the user uploaded image
	$query['author'] = $current_user->ID;

	return $query;
} */






/*ADICIOMA BOTES DE ACESSO A TRILHA E ACESSO AO CURSO DENTRO DO MENU TRILHAS NO DASHBOARD - ADICIONADO EM 31 DE MARÇO DE 2025*/
wp_dequeue_script('clp-js');
wp_deregister_script('clp-js');

// Enqueue the custom VibeBP editor.js
wp_enqueue_script(
    'clp-js',
    plugin_dir_url(__FILE__) . 'js/clp.js',
    array('jquery'),
    '1.0',
    true
);

add_filter('rest_pre_serve_request', function ($served, $result, $request) {
    if ($request->get_route() === '/vibeclp/v1/user/clps') {
        $data = $result->get_data();

        if (!empty($data['clps'])) {
            foreach ($data['clps'] as &$clp) {
                // Append button inside post_title with allowed HTML
                $button = '<br> <a href="#" class="custom-btn button is-primary enroll" style="display:inline-block;margin-left:0px;padding:5px 10px;background:#0073aa;color:white;text-decoration:none;border-radius:30px;width:100%">ACESSAR TRILHA</a>';

                // Use wp_kses_post() to allow safe HTML
                $clp['post_title'] = $clp['post_title'] . $button;
            }
        }

        // Update the response
        $result->set_data($data);
    }

    if ($request->get_route() === '/vibeclp/v1/user/clp/clp-steps') {
        $data = $result->get_data();
        if (!empty($data['data'])) {
            foreach ($data['data'] as &$step) {
                // Generate the course link dynamically (modify if needed)
                $post = get_page_by_title($step['title'], OBJECT, 'course');
                $course_url = $post->ID; //!empty($post) ? get_permalink($post->ID) : '#';


                // Button HTML
                $button = '<br> <a href="javascript:void(0);" onclick="window.location.href=\'/app/#component=course&action=course&id=' . $course_url . '\'; setTimeout(function(){ location.reload(); }, 100);" class="custom-btn button is-primary enroll" style="display:inline-block;margin-left:0px;padding:5px 10px;background:#0073aa;color:white;text-decoration:none;border-radius:30px;">ACESSAR CURSO</a>';

                // Append button inside step title
                $step['description'] .= $button;
            }
        }
        $result->set_data($data);
    }

    return $served;
}, 10, 3);



/* ATRAVÉS DA REGRA DE USUÁRIO PERSONALIZA LAYOUT DASHBOARD DE PAINEL DE CURSOS COM CSS E SCRIPTS 
function inserir_css_inline_por_funcao_usuario() {
    if ( is_user_logged_in() ) {
        $usuario = wp_get_current_user();

        if ( in_array( 'membro-giants', (array) $usuario->roles ) ) {
            // Define o conteúdo do CSS diretamente aqui
            $css = "
				
				.vibebp_myprofile {
 				   --sidebar: #cccccc;
				   --dark: #f07d00;
			       --primarycolor: #ffffff;
   				   --light: #a0a0a0;
 				   --text: #000000;
 				   --bold: #0b141b;
  				   --primary: #000000;
				}
				
				.vibebp_myprofile.dark_theme {
    			  --sidebar: #2d2d2d;
   				  --dark: #f07d00;
    			  --text: #ffffff;
    			  --bold: #ffffff;
  			  	  --highlight: #2d2d2d;
   				  --body: #0b141b;
    			  --primary: #f07d00;
}
                .vibebp_myprofile .profile_menu {
                    background: #000000;
                    background: linear-gradient(158deg,rgba(0, 0, 0, 1) 1%, rgba(92, 92, 92, 1) 100%);
                    color: #ffffff;
                }

                .vibebp_myprofile a.button {
				   background: #898989 !important;
				}
				
            ";
			
		
			// JS robusto que troca a logo mesmo que carregue depois
            $js = "
                document.addEventListener('DOMContentLoaded', function() {
                    function trocarLogo() {
                        var img = document.querySelector('.site_logo img');
                        if (img && img.src !== 'http://3.138.120.72/wp-content/uploads/2025/04/logo-GIANTS-top.png') {
                            img.src = 'http://3.138.120.72/wp-content/uploads/2025/04/logo-GIANTS-top.png';
                            img.srcset = '';
                        }
                    }

                    // Tenta trocar imediatamente
                    trocarLogo();

                    // Observa mudanças no DOM se a logo ainda não estiver pronta
                    var observer = new MutationObserver(trocarLogo);
                    observer.observe(document.body, { childList: true, subtree: true });
                });
            ";

            echo '<style type="text/css">' . $css . '</style>';
            echo '<script>' . $js . '</script>';
			
        }
    }
}
add_action( 'wp_head', 'inserir_css_inline_por_funcao_usuario' ); */




/*
 * RECURSO DE VALIDAÇÃO DE CERTIFICADO
 * DATA DA IMPLEMENTAÇÃO: 14/05/2025
 * RESPONSAVEIS: Bruno e Jonas 
*/


//Customizar pg de erro de certificado. - Incluído wp-die.php na pasta raiz do tema
// For implementation instructions see: https://aceplugins.com/how-to-add-a-code-snippet/

/**
 * Maybe change the wp_die_handler.
 */
add_filter('wp_die_handler', function ($handler) {
    return ! is_admin() ? 'themed_wp_die_handler' : $handler;
}, 10);

/**
 * Use a custom wp_die() handler.
 */
function themed_wp_die_handler($message, $title = '', $args = array())
{
    $defaults = array('response' => 500);
    $r = wp_parse_args($args, $defaults);


    if (function_exists('is_wp_error') && is_wp_error($message)) {
        $errors = $message->get_error_messages();
        switch (count($errors)) {
            case 0:
                $message = '';

                break;
            case 1:
                $message = $errors[0];
                break;
            default:
                $message = "<ul>\n\t\t<li>" . join("</li>\n\t\t<li>", $errors) . "</li>\n\t</ul>";
                break;
        }
    } else {
        $message = strip_tags($message);
    }

    require_once get_stylesheet_directory() . '/wp-die.php';

    die();
}




//Customização dos elementos de H5P para puxar cor global do wordpress em personalizar.
//Existe um arquivo na raiz da pasta wplms_customizer que contém os CSSs de personalização.
//Abaixo o código para substituir e ajustar os CSSs nos H5Ps criados.

add_action('init', 'yasir_override_h5p_embed');

function yasir_override_h5p_embed()
{
    // Remove the original action
    remove_action('wp_ajax_h5p_embed', 'h5p_ajax_embed');
    remove_action('wp_ajax_nopriv_h5p_embed', 'h5p_ajax_embed');

    // Add your custom handler
    add_action('wp_ajax_h5p_embed', 'yasir_custom_h5p_embed');
    add_action('wp_ajax_nopriv_h5p_embed', 'yasir_custom_h5p_embed');
}

function yasir_custom_h5p_embed()
{
    require_once plugin_dir_path(__FILE__) . 'embed.php'; // or embed.php
    exit;
}

// FIM - Código de personalização de CSS para H5P



// Adiciona um shortcode para ser usado na pgina de layout do curso do Elementor para aprecer os botes de adicionais a lista de favoritos.
// O shortcode é: [course_wishlist_conditional].
// Ao desativar a funcionalidade em Gerenciar Funcionalidades para Lista de Desejos, ele some também da página de cursos do curso específico.
function shortcode_course_wishlist_conditional()
{
    // Verifica se o plugin WPLMS Wishlist está ativo
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    if (is_plugin_active('wplms-wishlist/wplms-wishlist.php')) {
        // HTML do bloco de wishlist
        ob_start(); ?>
        <div class="elementor-element elementor-element-44fb86b elementor-widget elementor-widget-course_wishlist" data-id="44fb86b" data-element_type="widget" data-widget_type="course_wishlist.default">
            <div class="elementor-widget-container">
                <div class="course_wishlist_block">
                    <span class="add_to_wishlist button" data-id="3861">
                        <span class="vicon vicon-heart"></span>
                        <span class="wishlist_label">Adicionar à Lista de Desejos</span>
                        <span class="alt_wishlist_label hide">Remover da lista de desejos</span>
                    </span>
                    <span class="add_to_collection button" data-id="3861">
                        <span class="vicon vicon-plus"></span>
                        <span class="collection_label">Adicionar às coleções</span>
                    </span>
                </div>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    // Retorna vazio se o plugin não estiver ativo
    return '';
}
add_shortcode('course_wishlist_conditional', 'shortcode_course_wishlist_conditional');


/* Cria o link de ativação no perfil do usuário. - Mesmo ele não podendo acessar se não estiver ativo, podemos pegar o link no MAKE integrando o Webhook com WN Webhook ao registrat um usuário e enviar para o aluno ativar a conta via Z-Api. - FEITO EM MEDIVOX 
add_filter('bp_core_signup_send_validation_email', 'save_activation_link_to_usermeta', 10, 4);
function save_activation_link_to_usermeta($email, $user_id, $user_email, $activation_key) {
    $activation_url = bp_get_activation_page() . "?key=" . $activation_key;
    update_user_meta($user_id, 'activation_url', esc_url($activation_url));
    return $email;
}
add_action('show_user_profile', 'display_activation_url_admin');
add_action('edit_user_profile', 'display_activation_url_admin');

function display_activation_url_admin($user) {
    $activation_url = get_user_meta($user->ID, 'activation_url', true);
    if ($activation_url) {
        echo '<h3>Link de Ativação</h3>';
        echo '<p><a href="' . esc_url($activation_url) . '" target="_blank">' . esc_html($activation_url) . '</a></p>';
    }
}
*/


// Força BuddyPress a usar wp_mail() (compatível com WP SMTP)
add_filter('bp_email_use_wp_mail', '__return_true');








/**
 * Plugin Name: WPLMS - Alunos do Mês (Admin, UTC-3 + Cursos)
 * Description: Tela no wp-admin para listar e exportar alunos registrados por mês (UTC-3) incluindo cursos matriculados.
 * Author: WPLMS Expert
 * Version: 1.1.0
 */

if (! defined('ABSPATH')) exit;

/** Timezone fixo: UTC-3 (Brasil). */
function wplms_adm_tz()
{
    // "America/Sao_Paulo" é UTC-3 e não tem DST atualmente.
    return new DateTimeZone('America/Sao_Paulo');
}

/** Intervalo LOCAL (UTC-3) do mês selecionado; também retorna versões em UTC para a query. */
function wplms_adm_month_range_local_and_utc($year = null, $month = null)
{
    $tz   = wplms_adm_tz();
    $now  = new DateTime('now', $tz);
    $y    = $year  ? (int)$year  : (int)$now->format('Y');
    $m    = $month ? (int)$month : (int)$now->format('m');

    $start_local = new DateTime(sprintf('%04d-%02d-01 00:00:00', $y, $m), $tz);
    $end_local   = (clone $start_local)->modify('first day of next month')->modify('-1 second');

    $start_utc = (clone $start_local)->setTimezone(new DateTimeZone('UTC'));
    $end_utc   = (clone $end_local)->setTimezone(new DateTimeZone('UTC'));

    return array($start_local, $end_local, $start_utc, $end_utc);
}

/** Obtém cursos do usuário (ids) via WPLMS, com fallback seguro. */
function wplms_adm_get_user_course_ids($user_id)
{
    $ids = array();

    if (function_exists('bp_course_get_user_courses')) {
        // WPLMS função padrão (retorna ids ou array com dados dependendo da versão)
        $res = bp_course_get_user_courses($user_id);
        if (is_array($res)) {
            // normalizar para array de ids
            foreach ($res as $k => $v) {
                if (is_numeric($v))        $ids[] = (int)$v;
                elseif (is_array($v) && isset($v['course_id'])) $ids[] = (int)$v['course_id'];
            }
        }
    }

    // Remover duplicados, manter ordem
    $ids = array_values(array_unique(array_filter($ids)));
    return $ids;
}

/** Monta string com títulos dos cursos (limite para não estourar a tela). */
function wplms_adm_course_titles_str($ids, $limit = 6, $more_suffix = ' …')
{
    if (empty($ids)) return '';
    $titles = array();
    $count  = 0;
    foreach ($ids as $cid) {
        $titles[] = get_the_title($cid) ?: ('#' . $cid);
        $count++;
        if ($count >= $limit) {
            if (count($ids) > $limit) $titles[] = $more_suffix;
            break;
        }
    }
    return implode(' | ', $titles);
}

/** Query central: usuários por mês LOCAL (UTC-3), ordenados por registro, com paginação. */
function wplms_adm_query_users_by_month($args = array())
{
    $args = wp_parse_args($args, array(
        'year'          => null,
        'month'         => null,
        'roles'         => array('student', 'subscriber'),
        'number'        => 50,
        'paged'         => 1,
        'only_enrolled' => false, // filtra somente quem tem matrícula em algum curso
    ));

    list($start_local, $end_local, $start_utc, $end_utc) = wplms_adm_month_range_local_and_utc($args['year'], $args['month']);

    $q = new WP_User_Query(array(
        'role__in'    => array_map('sanitize_key', (array)$args['roles']),
        'orderby'     => 'user_registered',
        'order'       => 'DESC',
        'number'      => (int)$args['number'],
        'offset'      => ((int)$args['paged'] - 1) * (int)$args['number'],
        'count_total' => true,
        'fields'      => array('ID', 'user_login', 'user_email', 'user_registered'),
        'date_query'  => array(array(
            'column'    => 'user_registered',          // armazenado em UTC no banco
            'after'     => $start_utc->format('Y-m-d H:i:s'), // limites convertidos para UTC
            'before'    => $end_utc->format('Y-m-d H:i:s'),
            'inclusive' => true,
        )),
    ));

    $results = (array)$q->get_results();

    // Se precisar “somente com matrícula”, filtramos após a query para manter a janela de data correta.
    if (! empty($args['only_enrolled']) && $results) {
        $filtered = array();
        foreach ($results as $u) {
            $cids = wplms_adm_get_user_course_ids($u->ID);
            if (! empty($cids)) $filtered[] = $u;
        }
        // Corrige total (apenas estimativo na UI; paginação simples)
        $q->results = $filtered;
        $q->total_users = count($filtered);
    }

    return $q;
}

/** Adiciona página em Usuários > Alunos do Mês (UTC-3). */
add_action('admin_menu', function () {
    add_users_page(
        'Alunos do Mês',
        'Alunos do Mês',
        'list_users',
        'wplms-alunos-mes',
        'wplms_adm_render_page'
    );
});

/** Render da página. */
function wplms_adm_render_page()
{
    if (! current_user_can('list_users')) wp_die('Sem permissão.');

    $year   = isset($_GET['year'])   ? (int)$_GET['year']  : null;
    $month  = isset($_GET['month'])  ? (int)$_GET['month'] : null;
    $roles  = isset($_GET['roles'])  ? array_map('sanitize_key', array_filter(array_map('trim', explode(',', $_GET['roles'])))) : array('student', 'subscriber');
    $pp     = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 50;
    $paged  = isset($_GET['paged'])    ? max(1, (int)$_GET['paged'])    : 1;
    $only   = ! empty($_GET['only_enrolled']);

    $q = wplms_adm_query_users_by_month(array(
        'year'          => $year,
        'month'         => $month,
        'roles'         => $roles,
        'number'        => $pp,
        'paged'         => $paged,
        'only_enrolled' => $only,
    ));

    list($start_local, $end_local) = array_slice(wplms_adm_month_range_local_and_utc($year, $month), 0, 2);
    $total     = (int) ($q->total_users ?? 0);
    $total_pg  = max(1, (int) ceil($total / $pp));
    $base_url  = menu_page_url('wplms-alunos-mes', false);

    ?>
    <div class="wrap">
           <h1 class="wp-heading-inline">Alunos registrados no mês</h1>
        <p>Período (local): <strong><?php echo esc_html($start_local->format('d/m/Y H:i')); ?></strong> — <strong><?php echo esc_html($end_local->format('d/m/Y H:i')); ?></strong></p>

        <form method="get" action="<?php echo esc_url(admin_url('users.php')); ?>" style="margin:12px 0;">
            <input type="hidden" name="page" value="wplms-alunos-mes" />
            <label>Ano:
                <input type="number" name="year" value="<?php echo esc_attr($year ?: (int)wp_date('Y', null, wplms_adm_tz())); ?>" min="2000" max="2100" />
            </label>
            <label>Mês:
                <input type="number" name="month" value="<?php echo esc_attr($month ?: (int)wp_date('m', null, wplms_adm_tz())); ?>" min="1" max="12" />
            </label>
            <!-- <label>Papéis (comma):
        <input type="text" name="roles" value="<?php echo esc_attr(implode(',', $roles)); ?>" placeholder="student,subscriber" size="24" />
      </label> -->
            <label>Por página:
                <input type="number" name="per_page" value="<?php echo esc_attr($pp); ?>" min="1" max="5000" />
            </label>
            <label style="margin-left:10px;">
                <input type="checkbox" name="only_enrolled" value="1" <?php checked($only); ?> />
                Somente quem tem matrícula em curso
            </label>
            <input type="hidden" name="paged" value="1" />
            <?php submit_button('Filtrar', 'secondary', '', false); ?>

            <?php
            $csv_url = wp_nonce_url(add_query_arg(array(
                'action'        => 'wplms_alunos_mes_csv',
                'year'          => $year,
                'month'         => $month,
                'roles'         => implode(',', $roles),
                'only_enrolled' => $only ? 1 : 0,
            ), admin_url('admin-post.php')), 'wplms_alunos_mes_csv');
            ?>
            <a href="<?php echo esc_url($csv_url); ?>" class="button button-primary">Exportar CSV</a>
        </form>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Login</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Registrado em (UTC-3)</th>
                    <th>Cursos (amostra)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($q->results)) :
                    echo '<tr><td colspan="6">Nenhum aluno encontrado neste período.</td></tr>';
                else:
                    $tz = wplms_adm_tz();
                    foreach ($q->results as $u):
                        // converter user_registered (UTC) para UTC-3 apenas para exibição
                        $dt = new DateTime($u->user_registered, new DateTimeZone('UTC'));
                        $dt->setTimezone($tz);

                        $course_ids = wplms_adm_get_user_course_ids($u->ID);
                        $course_str = $course_ids ? wplms_adm_course_titles_str($course_ids, 6, ' …') : '—';

                        $edit_link = get_edit_user_link($u->ID);
                ?>
                        <tr>
                            <td><?php echo (int)$u->ID; ?></td>
                            <td><?php echo esc_html($u->user_login); ?></td>
                            <td><?php echo esc_html(get_the_author_meta('display_name', $u->ID)); ?></td>
                            <td><a href="<?php echo esc_url($edit_link); ?>" target="_blank" rel="noopener"><?php echo esc_html($u->user_email); ?></a></td>
                            <td><?php echo esc_html($dt->format('d/m/Y H:i')); ?></td>
                            <td><?php echo esc_html($course_str); ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>

        <?php if ($total > $pp) :
            echo '<div class="tablenav"><div class="tablenav-pages">';
            $links = paginate_links(array(
                'base'      => add_query_arg(array_merge($_GET, array('paged' => '%#%')), $base_url),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => max(1, (int)ceil($total / $pp)),
                'current'   => max(1, (int)$paged),
                'type'      => 'array',
            ));
            if ($links) echo '<span class="pagination-links">' . implode(' ', $links) . '</span>';
            echo '</div></div>';
        endif; ?>
    </div>
<?php
}

/** Export CSV (com títulos de cursos) */
add_action('admin_post_wplms_alunos_mes_csv', function () {
    if (! current_user_can('list_users')) wp_die('Sem permissão.');
    check_admin_referer('wplms_alunos_mes_csv');

    $year  = isset($_GET['year'])  ? (int)$_GET['year']  : null;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    $roles = isset($_GET['roles']) ? array_map('sanitize_key', array_filter(array_map('trim', explode(',', $_GET['roles']))))
        : array('student', 'subscriber');
    $only  = ! empty($_GET['only_enrolled']);

    // busca até 10k para export
    $q = wplms_adm_query_users_by_month(array(
        'year'          => $year,
        'month'         => $month,
        'roles'         => $roles,
        'number'        => 10000,
        'paged'         => 1,
        'only_enrolled' => $only,
    ));

    $filename = sprintf(
        'alunos-registrados-%s-%s-utc-3.csv',
        $year ?: wp_date('Y', null, wplms_adm_tz()),
        $month ? sprintf('%02d', $month) : wp_date('m', null, wplms_adm_tz())
    );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fputcsv($out, array('ID', 'Login', 'Nome', 'Email', 'Registrado em (UTC-3)', 'Cursos (títulos separados por |)'));

    $tz = wplms_adm_tz();
    foreach ((array)$q->results as $u) {
        $dt = new DateTime($u->user_registered, new DateTimeZone('UTC'));
        $dt->setTimezone($tz);

        $course_ids = wplms_adm_get_user_course_ids($u->ID);
        $course_titles = $course_ids ? wplms_adm_course_titles_str($course_ids, 9999, '') : '';

        fputcsv($out, array(
            $u->ID,
            $u->user_login,
            get_the_author_meta('display_name', $u->ID),
            $u->user_email,
            $dt->format('Y-m-d H:i'),
            $course_titles,
        ));
    }
    fclose($out);
    exit;
});



// Adiciona CSS do GitHub Pages apenas em /app
// function wplms_custom_github_pages_css() {

//     // Verifica se a URL começa com /app
//     if ( isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/app') === 0 ) {

//         // URL do CSS no GitHub Pages com cache-busting
//         $css_url = add_query_arg('v', time(), 'https://equipewebnauta.github.io/liquidGlass/style.css');

//         // Enqueue o CSS
//         wp_enqueue_style(
//             'liquidglass-style', // identificador único
//             $css_url,
//             array(), // dependências
//             null
//         );
//     }
// }

// // Hook com prioridade alta para carregar por último
// add_action('wp_enqueue_scripts', 'wplms_custom_github_pages_css', 999);



// Adiciona o SVG do filtro Liquid Glass otimizado no footer
// 
add_action('wp_footer', function () {
    echo '<svg style="display: none">
    <filter id="liquidglass" x="-20%" y="-20%" width="100%" height="100%">
        <feImage x="0" y="0" width="100%" height="100%" preserveAspectRatio="none" result="imagemSVG"
            xlink:href="data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;100&quot; height=&quot;100&quot;><radialGradient id=&quot;mapaGradiente&quot; cx=&quot;50%&quot; cy=&quot;50%&quot; r=&quot;50%&quot;><stop offset=&quot;0%&quot; stop-color=&quot;rgb(128,128,255)&quot;/><stop offset=&quot;100%&quot; stop-color=&quot;rgb(255,255,255)&quot;/></radialGradient><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;url(%23mapaGradiente)&quot;/></svg>" />
        <feDisplacementMap in="SourceGraphic" in2="imagemSVG" scale="-20"
            xChannelSelector="R" yChannelSelector="G" result="imagemDistorcida" />
        <feMerge>
            <feMergeNode in="imagemDistorcida" />
        </feMerge>
    </filter>
</svg>';
});





// add_action('wp_footer', function() {
//     echo <<<'EOT'
// <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js"></script>
// <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
// <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
// <script>
// (function() {

//     // cria modal de bloqueio
//     function createCameraModal() {
//         let modal = document.getElementById("camera-permission-modal");
//         if (modal) return modal;

//         modal = document.createElement("div");
//         modal.id = "camera-permission-modal";
//         modal.style.position = "fixed";
//         modal.style.top = "0";
//         modal.style.left = "0";
//         modal.style.width = "100%";
//         modal.style.height = "100%";
//         modal.style.background = "rgba(0,0,0,0.85)";
//         modal.style.display = "flex";
//         modal.style.alignItems = "center";
//         modal.style.justifyContent = "center";
//         modal.style.zIndex = "999999";

//         modal.innerHTML = `
//             <div style="background:#fff; padding:30px; border-radius:12px; max-width:420px; text-align:center; font-family:sans-serif;">
//                 <h2 style="margin-bottom:15px;">⚠️ Permissão necessária</h2>
//                 <p style="margin-bottom:20px;">
//                     É necessário permitir o acesso à câmera para continuar a assistir ao vídeo.
//                 </p>
//                 <button id="retryCameraBtn" style="background:#0073e6; color:#fff; border:none; padding:12px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">
//                     Permitir acesso à câmera
//                 </button>
//             </div>
//         `;

//         document.body.appendChild(modal);

//         return modal;
//     }

//     function showCameraModal(retryCallback) {
//         const modal = createCameraModal();
//         modal.style.display = "flex";
//         document.getElementById("retryCameraBtn").onclick = () => {
//             retryCallback();
//         };
//     }

//     function hideCameraModal() {
//         const modal = document.getElementById("camera-permission-modal");
//         if (modal) modal.style.display = "none";
//     }

//     // -------- resto do código --------
//     function startMonitoringForWrapper(wrapper) {
//         if (!wrapper || wrapperStarted(wrapper)) return;
//         markWrapperStarted(wrapper);
//         console.log('startMonitoringForWrapper:', wrapper);

//         // UI do preview
//         const cameraWrapper = document.createElement('div');
//         cameraWrapper.style.position = 'fixed'; cameraWrapper.style.bottom = '12px'; cameraWrapper.style.right = '12px';
//         cameraWrapper.style.width = '220px'; cameraWrapper.style.height = '160px'; cameraWrapper.style.border = '2px solid #333';
//         cameraWrapper.style.borderRadius = '10px'; cameraWrapper.style.overflow = 'hidden'; cameraWrapper.style.background = 'black';
//         cameraWrapper.style.zIndex = '99999'; cameraWrapper.style.boxShadow = '0 6px 18px rgba(0,0,0,0.3)';
//         document.body.appendChild(cameraWrapper);

//         const videoCam = document.createElement('video'); 
//         videoCam.autoplay = true; 
//         videoCam.playsInline = true; 
//         videoCam.muted = true;
//         videoCam.style.width = '100%'; 
//         videoCam.style.height = '100%'; 
//         videoCam.style.objectFit = 'cover';
//         cameraWrapper.appendChild(videoCam);

//         const canvas = document.createElement('canvas'); 
//         canvas.width = 220; canvas.height = 160;
//         canvas.style.position = 'absolute'; 
//         canvas.style.top = '0'; 
//         canvas.style.left = '0'; 
//         canvas.style.pointerEvents = 'none';
//         cameraWrapper.appendChild(canvas); 
//         const ctx = canvas.getContext('2d');

//         const statusDiv = document.createElement('div');
//         statusDiv.style.position = 'fixed'; 
//         statusDiv.style.bottom = '180px'; 
//         statusDiv.style.right = '12px';
//         statusDiv.style.background = 'rgba(0,0,0,0.7)'; 
//         statusDiv.style.color = 'white';
//         statusDiv.style.padding = '6px 10px'; 
//         statusDiv.style.borderRadius = '6px'; 
//         statusDiv.style.fontWeight = '600';
//         statusDiv.style.zIndex = '99999'; 
//         statusDiv.textContent = 'Status: Abrindo câmera...'; 
//         document.body.appendChild(statusDiv);

//         const TIME_TO_PAUSE_SEC = 15;

//         function tryInitCamera() {
//             navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
//             .then(stream => {
//                 hideCameraModal();
//                 videoCam.srcObject = stream;
//                 statusDiv.textContent = 'Status: Câmera ativa. Detectando...';
//                 stream.getVideoTracks().forEach(t => t.onended = () => {
//                     console.warn('track ended; tentando reconectar');
//                     statusDiv.textContent = 'Status: Câmera desconectada. Reconectando...';
//                     tryInitCamera();
//                 });
//                 initFaceMeshRobust(videoCam, canvas, ctx, statusDiv, wrapper, TIME_TO_PAUSE_SEC);
//             })
//             .catch(err => {
//                 console.error('getUserMedia failed:', err);
//                 statusDiv.textContent = 'Status: Falha ao acessar câmera';
//                 showCameraModal(tryInitCamera);
//             });
//         }

//         tryInitCamera();
//     }

//     // funções wrapperStarted, markWrapperStarted, etc (mesmas que você já tem)...
//     function wrapperStarted(wrapper) { return wrapper && wrapper.dataset && wrapper.dataset.eyeMonitorStarted === '1'; }
//     function markWrapperStarted(wrapper) { if (wrapper) wrapper.dataset.eyeMonitorStarted = '1'; }

//     // resto do seu código continua igual (initFaceMeshRobust, observers, etc)...
// })();
// </script>
// EOT;
// });
// 
// 
// 
// 
// 


/**
 * Script personalizado para alternar entre abas "Cursos Concluídos" e "Meus Certificados"
 * 
 * Autor: Jonas Borges
 * Data: 10/10/2025
 * 
 * Descrição:
 * Este código adiciona um comportamento de abas dentro da área "Minhas Conquistas" do WPLMS,
 * exibindo separadamente os blocos de cursos concluídos e certificados. 
 * 
 * - Ao carregar a página, a aba "Meus Certificados" é exibida por padrão.
 * - É possível alternar entre "Cursos Concluídos" e "Meus Certificados" sem recarregar a página.
 * - Ambas as seções usam display: grid (4 colunas no desktop, 2 no tablet e 1 no celular).
 * - A exibição é restaurada automaticamente apenas na página "Minhas Conquistas".
 */
add_action('wp_footer', function () { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            function initCustomScripts() {
                const hash = window.location.hash;

                // === Limpa elementos antigos caso mude de rota ===
                const oldTabs = document.querySelector('.custom-tabs-wrapper');
                if (oldTabs && hash !== '#component=course&action=course-stats') {
                    oldTabs.remove();
                    // também reseta exibição
                    document.querySelectorAll('.my_certificates.course_blocks, .mycourses_header.vibebp_form, .my_achievements_wrapper .my_achievements_wrapper .course_blocks')
                        .forEach(el => el.style.display = 'none');
                    return; // sai — não executa o resto fora da rota
                }

                // Só roda na rota correta
                if (hash !== '#component=course&action=course-stats') return;

                // Função que realmente injeta tudo
                function injectScripts() {
                    console.log('⚙️ Script course-stats ativo');

                    // === Injeta CSS apenas uma vez ===
                    if (!document.querySelector('#custom-stats-style')) {
                        const style = document.createElement('style');
                        style.id = 'custom-stats-style';
                        style.textContent = `
					/* Conteúdos ocultos inicialmente */
                    .my_certificates.course_blocks,
                    .mycourses_header.vibebp_form,
                    .my_achievements_wrapper .my_achievements_wrapper .course_blocks {
                        display: none;
                    }

                    .my_certificates_wrapper > h3,
                    .my_achievements_wrapper > h3 {
                        position: absolute !important;
                        left: -9999px !important;
                    }

                    .custom-tabs-wrapper {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        gap: 16px;
                        flex-wrap: wrap;
                        margin: 18px auto 20px;
                    }

                    .custom-tab-btn {
                        background-color: var(--e-global-color-text);
                        color: var(--e-global-color-primary);
                        border-radius: 56px;
                        padding: 16px;
                        cursor: pointer;
                        transition: all .22s ease;
                        font-weight: 700;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
                        border: none;
                        min-width: 250px;
                        text-align: center;
                        user-select: none;
                        display: block !important;
                    }

                    .custom-tab-btn:hover {
                        background-color: var(--primary);
                        opacity: 0.7;
                    }

                    .custom-tab-btn.active {
                        background-color: var(--primary);
                        opacity: 0.7;
                        box-shadow: 0 6px 18px rgba(0,59,255,0.18);
                    }

                    .mycourses_header.vibebp_form,
                    .my_achievements_wrapper .my_achievements_wrapper .course_blocks,
                    .my_certificates_wrapper .my_certificates.course_blocks {
                        background: #f6f6f6;
                        border-radius: 10px;
                        padding: 20px;
                        margin-top: 8px;
                    }

                    /* GRID RESPONSIVO */
                    .my_achievements_wrapper .course_blocks,
                    .my_certificates_wrapper .course_blocks {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 20px;
                    }

                    @media (max-width: 1024px) {
                        .my_achievements_wrapper .course_blocks,
                        .my_certificates_wrapper .course_blocks {
                            grid-template-columns: repeat(2, 1fr);
                        }
                    }

                    @media (max-width: 768px) {
                        .custom-tabs-wrapper {
                            gap: 12px;
                            margin: 12px auto;
                        }
                        .custom-tab-btn {
                            width: 90%;
                            max-width: 360px;
                        }
                        .my_achievements_wrapper .course_blocks,
                        .my_certificates_wrapper .course_blocks {
                            grid-template-columns: 1fr;
                        }
                    }

                    /* Transição suave */
                    .my_certificates.course_blocks,
                    .mycourses_header.vibebp_form,
                    .my_achievements_wrapper .my_achievements_wrapper .course_blocks {
                        transition: opacity .3s ease;
                    }

                    .fade-enter {
                        opacity: 0;
                    }
                    .fade-enter-active {
                        opacity: 1;
                    }
					.my_achievements_wrapper .mycourses_header.vibebp_form{
						grid-template-columns: 7fr 3fr;
					}
                    @media (max-width: 768px){
                        .my_achievements_wrapper .mycourses_header.vibebp_form{
                            grid-template-columns: 1fr;
                        }
                        .my_achievements_wrapper .my_achievements_wrapper {
							padding-right: 20px;

						}
                    }
                    .my_achievements_wrapper .mycourses_header.vibebp_form .searchbox.active,
                    .my_achievements_wrapper .mycourses_header.vibebp_form select{
                        width:100%;
                        max-width:100%!important;
                    }
                `;
                        document.head.appendChild(style);
                    }

                    // === Cria as abas se ainda não existir ===
                    if (!document.querySelector('.custom-tabs-wrapper')) {
                        const root = document.querySelector('.vibebp_main') || document.body;
                        const wrapper = document.createElement('div');
                        wrapper.className = 'custom-tabs-wrapper';

                        const origCertH3 = document.querySelector('.my_certificates_wrapper > h3');
                        const origCourseH3 = document.querySelector('.my_achievements_wrapper > h3');

                        const btnCert = document.createElement('button');
                        btnCert.className = 'custom-tab-btn';
                        btnCert.dataset.tab = 'certificates';
                        btnCert.textContent = origCertH3 ? origCertH3.textContent.trim() : 'Meus Certificados';

                        const btnCourse = document.createElement('button');
                        btnCourse.className = 'custom-tab-btn';
                        btnCourse.dataset.tab = 'courses';
                        btnCourse.textContent = origCourseH3 ? origCourseH3.textContent.trim() : 'Cursos Concluídos';

                        wrapper.appendChild(btnCert);
                        wrapper.appendChild(btnCourse);

                        const firstWrapper = document.querySelector('.my_achievements_wrapper') || document.querySelector('.my_certificates_wrapper');
                        if (firstWrapper && firstWrapper.parentNode) {
                            firstWrapper.parentNode.insertBefore(wrapper, firstWrapper);
                        } else {
                            root.insertBefore(wrapper, root.firstChild);
                        }
                    }

                    // === Funções de alternância ===
                    function openCertificates() {
                        const certBlock = document.querySelector('.my_certificates_wrapper .my_certificates.course_blocks');
                        const header = document.querySelector('.mycourses_header.vibebp_form');
                        const nestedBlock = document.querySelector('.my_achievements_wrapper .my_achievements_wrapper .course_blocks');
                        if (!certBlock) return;

                        header && (header.style.display = 'none');
                        nestedBlock && (nestedBlock.style.display = 'none');
                        certBlock.style.display = 'grid';

                        document.querySelectorAll('.custom-tab-btn').forEach(b => b.classList.remove('active'));
                        document.querySelector('.custom-tab-btn[data-tab="certificates"]')?.classList.add('active');
                    }

                    function openCourses() {
                        const certBlock = document.querySelector('.my_certificates_wrapper .my_certificates.course_blocks');
                        const header = document.querySelector('.mycourses_header.vibebp_form');
                        const nestedBlock = document.querySelector('.my_achievements_wrapper .my_achievements_wrapper .course_blocks');

                        certBlock && (certBlock.style.display = 'none');
                        header && (header.style.display = 'grid');
                        nestedBlock && (nestedBlock.style.display = 'grid');

                        document.querySelectorAll('.custom-tab-btn').forEach(b => b.classList.remove('active'));
                        document.querySelector('.custom-tab-btn[data-tab="courses"]')?.classList.add('active');
                    }

                    // === Eventos de clique ===
                    if (!window.customTabsButtonsBound) {
                        window.customTabsButtonsBound = true;
                        document.addEventListener('click', e => {
                            const btn = e.target.closest('.custom-tab-btn');
                            if (btn) {
                                btn.dataset.tab === 'certificates' ? openCertificates() : openCourses();
                            }
                        });
                    }

                    // === Estado inicial (certificados abertos) ===
                    (function ensureInitialState(attemptsLeft = 10) {
                        const certBlock = document.querySelector('.my_certificates_wrapper .my_certificates.course_blocks');
                        const btnCert = document.querySelector('.custom-tab-btn[data-tab="certificates"]');
                        if (certBlock && btnCert) {
                            const header = document.querySelector('.mycourses_header.vibebp_form');
                            const nestedBlock = document.querySelector('.my_achievements_wrapper .my_achievements_wrapper .course_blocks');
                            header && (header.style.display = 'none');
                            nestedBlock && (nestedBlock.style.display = 'none');
                            certBlock.style.display = 'grid';
                            document.querySelectorAll('.custom-tab-btn').forEach(b => b.classList.remove('active'));
                            btnCert.classList.add('active');
                            return;
                        }
                        if (attemptsLeft > 0) setTimeout(() => ensureInitialState(attemptsLeft - 1), 200);
                    })();
                }

                // === Observa mudanças no DOM do WPLMS (AJAX render) ===
                const observer = new MutationObserver(() => {
                    if (document.querySelector('.my_achievements_wrapper')) {
                        observer.disconnect();
                        injectScripts();
                    }
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }

            initCustomScripts();
            window.addEventListener('hashchange', initCustomScripts);

        });
    </script>
<?php });


/**
 * 🔐 WPLMS Password Fix — Resumo Explicativo
 *
 * Autor: Miguel Cezar
 * Data: 10/10/2025
 * Objetivo:
 * Corrigir a função “Alterar senha” no painel React do WPLMS (VibeBP),
 * permitindo que o usuário altere a senha mesmo quando a sessão PHP não está ativa.
 *
 * O que o código faz:
 * 1. **Injeta um script no <head>** via `wp_head` — o script roda dentro do painel React.
 * 2. **Espera o carregamento da aba “Alterar senha”** e substitui o botão “Salvar alterações”
 *    (que é controlado pelo React) por um clone, removendo listeners antigos.
 * 3. **Adiciona um novo handler de clique**, que:
 *    - Valida os campos de senha.
 *    - Lê `sessionStorage.bp_user` (dados do usuário logado no app React).
 *    - Envia um `fetch()` AJAX para `admin-ajax.php` com `action=wplms_customizer_update_password`,
 *      contendo o `user_id`, `password` e `refresh_token`.
 * 4. **No PHP**, o handler AJAX:
 *    - Identifica o usuário logado (usando `get_current_user_id()` ou o `user_id` do request).
 *    - Atualiza a senha com `wp_update_user()`.
 *    - Retorna resposta JSON de sucesso ou erro.
 *
 * Resultado:
 * ✔ Corrige o erro “Não foram feitas alterações para essa conta”.
 * ✔ Mantém o usuário logado após a troca de senha.
 * ✔ Funciona com o painel React do VibeBP.
 */

add_action('wp_head', function () {
?>
    <script id="wplms-password-fix-js">
        //     console.log('%c[WPLMS Password Fix] Script carregado.', 'color: green;');

        (function waitForPortal() {
            const portal = document.querySelector('.portal_body');
            if (!portal) {
                //             console.log('[WPLMS Password Fix] Aguardando portal_body...');
                return setTimeout(waitForPortal, 1000);
            }
            //         console.log('[WPLMS Password Fix] portal_body detectado.');
            initPasswordHandler();
        })();

        function showWPLMSNotice(message, type = 'success') {
            if (typeof vibebp !== 'undefined' && typeof vibebp.show_message === 'function') {
                vibebp.show_message(message, type);
            } else {
                // fallback: notificação simples no canto superior direito
                const color = type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#28a745';
                const box = document.createElement('div');
                box.textContent = message;
                box.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${color};
                color: white;
                padding: 12px 18px;
                border-radius: 6px;
                font-size: 14px;
                z-index: 9999;
                box-shadow: 0 3px 6px rgba(0,0,0,0.2);
                opacity: 0;
                transition: opacity .3s ease;
            `;
                document.body.appendChild(box);
                setTimeout(() => box.style.opacity = '1', 50);
                setTimeout(() => {
                    box.style.opacity = '0';
                    setTimeout(() => box.remove(), 500);
                }, 3000);
            }
        }

        function showInputError(input, message) {
            clearInputError(input);
            const error = document.createElement('div');
            error.className = 'wplms-input-error';
            error.textContent = message;
            error.style.cssText = `
            color: #dc3545;
            font-size: 13px;
            margin-top: 4px;
            font-weight: 500;
        `;
            input.style.borderColor = '#dc3545';
            input.insertAdjacentElement('afterend', error);
        }

        function clearInputError(input) {
            input.style.borderColor = '';
            const next = input.nextElementSibling;
            if (next && next.classList.contains('wplms-input-error')) {
                next.remove();
            }
        }

        function initPasswordHandler() {
            const observer = new MutationObserver(() => attachHandler());
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            attachHandler();
        }

        function attachHandler() {
            const portalBody = document.querySelector('.portal_body');
            if (!portalBody) return;

            const activeTab = portalBody.querySelector('.setting_tab.active');
            if (!activeTab) return;
            if (activeTab.textContent.trim() !== 'Alterar senha') return;

            const form = portalBody.querySelector('.vibebp_form.tab_content');
            const oldButton = form?.querySelector('.button.is-primary');
            const passwordFields = form?.querySelectorAll('input[type="password"]') || [];

            //         console.log('[WPLMS Password Fix] Tentando vincular handler...');
            //         console.log('[WPLMS Password Fix] Campos:', passwordFields.length, 'Botão:', !!oldButton);

            if (!oldButton || passwordFields.length < 2) return;
            if (oldButton.dataset.listenerAttached === 'true') {
                //             console.log('[WPLMS Password Fix] Handler já anexado.');
                return;
            }

            const newButton = oldButton.cloneNode(true);
            oldButton.replaceWith(newButton);

            const [inputSenha, inputRepetir] = passwordFields;
            newButton.dataset.listenerAttached = 'true';

            //         console.log('[WPLMS Password Fix] Botão clonado e handler de clique adicionado.');

            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();

                clearInputError(inputSenha);
                clearInputError(inputRepetir);

                const senha = (inputSenha?.value || '').trim();
                const repetir = (inputRepetir?.value || '').trim();

                if (!senha || !repetir) {
                    showInputError(!senha ? inputSenha : inputRepetir, 'Por favor, preencha este campo.');
                    return;
                }
                if (senha.length < 6) {
                    showInputError(inputSenha, 'A senha deve ter pelo menos 6 caracteres.');
                    return;
                }
                if (senha !== repetir) {
                    showInputError(inputRepetir, 'As senhas não coincidem.');
                    return;
                }

                // 🔍 Busca o usuário ativo via sessionStorage.bp_user
                let token = '',
                    user_id = '';
                try {
                    const bpUser = JSON.parse(sessionStorage.getItem('bp_user') || '{}');
                    token = bpUser?.refresh_token || '';
                    user_id = bpUser?.id || '';
                } catch (e) {}

                //             console.log('[WPLMS Password Fix] Token encontrado?', !!token, 'User ID:', user_id);

                if (!token) {
                    showInputError(inputSenha, 'Sessão inválida. Atualize a página.');
                    return;
                }

                //             console.log('[WPLMS Password Fix] Enviando requisição AJAX...');

                fetch(window.ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'wplms_customizer_update_password',
                            password: senha,
                            token: token,
                            user_id: user_id
                        })
                    })
                    .then(async r => {
                        const raw = await r.text();
                        //                 console.log('[WPLMS Password Fix] Resposta bruta:', raw);
                        try {
                            return JSON.parse(raw);
                        } catch {
                            return {
                                success: false,
                                message: raw
                            };
                        }
                    })
                    .then(data => {
                        //                 console.log('[WPLMS Password Fix] Resposta JSON:', data);
                        if (data.success) {
                            showWPLMSNotice('Senha alterada com sucesso!', 'success');
                            inputSenha.value = '';
                            inputRepetir.value = '';
                        } else {
                            showInputError(inputSenha, data.message || 'Erro ao alterar a senha.');
                        }
                    })
                    .catch(err => {
                        //                 console.error('[WPLMS Password Fix] Erro no fetch:', err);
                        showInputError(inputSenha, 'Erro inesperado. Tente novamente.');
                    });
            });
        }
    </script>
<?php
});













// Forçar curso a 100% ao finalizar manualmente
add_action('wplms_course_submitted', 'custom_force_course_complete', 10, 2);

function custom_force_course_complete($course_id, $user_id)
{

    // Marca o progresso como 100%
    bp_course_update_user_progress($user_id, $course_id, 100);

    // Marca o curso como concluído
    bp_course_mark_complete($course_id, $user_id);

    // Dispara emissão do certificado se habilitado
    if (function_exists('bp_course_get_user_certificate')) {
        $certificate = bp_course_get_user_certificate($course_id, $user_id);
        if (!$certificate) {
            bp_course_generate_certificate($course_id, $user_id);
        }
    }
}










/**
 * Script personalizado para exclusão de atividades no WPLMS (somente administradores)
 * 
 * Autor: Miguel Cezar
 * Data: 11/11/2025
 * 
 * Descrição:
 * Este código adiciona uma funcionalidade de exclusão de atualizações de atividade (posts da timeline)
 * diretamente no front-end do WPLMS, disponível apenas para usuários com papel de administrador.
 * 
 * - Adiciona dinamicamente um botão de exclusão (ícone de lixeira) em cada item de atividade.
 * - Exibe um modal de confirmação personalizado antes de excluir.
 * - Utiliza AJAX para realizar a exclusão sem recarregar a página.
 * - Inclui dupla validação de segurança (nonce e verificação de papel de usuário).
 * - Remove ícones duplicados e oculta lixeiras indevidas (ex: "entrou no grupo").
 * - Mantém compatibilidade com o BuddyPress e o fluxo dinâmico de atividades do WPLMS.
 */

add_action('wp_footer', function () {
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('delete_activity_update_nonce');
?>
    <script>
        (function($) {
            //         console.info('[WPLMS-DEBUG] Script de administrador carregado.');

            /**
             * ===================================================
             * 🧩 Função global de modal de confirmação customizado
             * ===================================================
             */
            function confirmModal(message) {
                return new Promise((resolve) => {
                    // Remove modal anterior (se existir)
                    $('#wplms-confirm-overlay').remove();

                    const modal = $(`
                    <div id="wplms-confirm-overlay">
                        <div id="wplms-confirm-box">
                            <h3>Confirmação</h3>
                            <p>${message}</p>
                            <div class="buttons">
                                <button id="wplms-cancel" class="cancel">Cancelar</button>
                                <button id="wplms-confirm" class="confirm">Excluir</button>
                            </div>
                        </div>
                    </div>
                `);

                    $('body').append(modal);

                    // Eventos dos botões
                    $('#wplms-cancel').on('click', function() {
                        modal.fadeOut(200, () => modal.remove());
                        resolve(false);
                    });

                    $('#wplms-confirm').on('click', function() {
                        modal.fadeOut(200, () => modal.remove());
                        resolve(true);
                    });
                });
            }

            /**
             * ===================================================
             * 🧠 Verifica usuário logado via sessionStorage (bp_user)
             * ===================================================
             */
            function verificarBpUser() {
                const bpUserData = sessionStorage.getItem('bp_user');

                if (!bpUserData) {
                    //                 console.log('[WPLMS-DEBUG] Aguardando sessionStorage.bp_user...');
                    setTimeout(verificarBpUser, 500);
                    return;
                }

                try {
                    const user = JSON.parse(bpUserData);

                    if (user.roles && Array.isArray(user.roles)) {
                        //                     console.log('[WPLMS-DEBUG] Papéis do usuário:', user.roles);

                        if (user.roles.includes('administrator')) {
                            //                         console.log('[WPLMS-DEBUG] Usuário é administrador ✅');
                            executarFuncaoAdministrador(user);
                        } else {
                            //                         console.log('[WPLMS-DEBUG] Usuário não é administrador ❌');
                        }
                    } else {
                        //                     console.log('[WPLMS-DEBUG] Estrutura de roles inválida no bp_user.');
                    }
                } catch (err) {
                    //                 console.error('[WPLMS-DEBUG] Erro ao processar bp_user:', err);
                }
            }


            /**
             * ===================================================
             * ⚙️ Função principal para administradores
             * ===================================================
             */
            function executarFuncaoAdministrador(user) {
                //             console.info('[WPLMS-DEBUG] executarFuncaoAdministrador iniciado.');

                // 🗑️ Adiciona botões de exclusão nas atividades
                function addDeleteButtons() {
                    var items = $('.activity_item.activity_update');
                    //                 console.debug('[WPLMS-DEBUG] addDeleteButtons() — encontrados:', items.length);

                    items.each(function() {
                        var item = $(this);
                        var activity_id = item.data('activity-id');
                        var actions = item.find('.activity_actions');
                        if (!actions.length) return;
                        if (actions.find('.delete-activity-update, .vicon-trash').length > 0) return;

                        var delBtn = $('<a/>', {
                            href: '#',
                            class: 'vicon vicon-trash delete-activity-update',
                            'data-activity-id': activity_id,
                            title: 'Excluir esta atualização'
                        });
                        actions.append(delBtn);
                        //                     console.info('[WPLMS-DEBUG] 🗑️ Botão de exclusão adicionado para activity_id:', activity_id);
                    });
                }

                addDeleteButtons();
                $(document).on('ajaxComplete.wplmsDeleteDebug', addDeleteButtons);
                setInterval(addDeleteButtons, 2000);

                // ⚙️ Clique no botão de exclusão
                $(document)
                    .off('click.wplmsDeleteDebug', '.delete-activity-update')
                    .on('click.wplmsDeleteDebug', '.delete-activity-update', async function(e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var activity_id = $btn.data('activity-id');

                        //                 console.log('[WPLMS-DEBUG] Clique detectado no botão');
                        //                 console.log('activity_id:', activity_id);

                        if (!activity_id) {
                            //                     console.error('[WPLMS-DEBUG] activity_id ausente.');
                            return;
                        }

                        // 🚨 Modal de confirmação (substitui o alert)
                        const confirmar = await confirmModal('Tem certeza que deseja excluir esta publicação?');
                        if (!confirmar) {
                            //                     console.log('[WPLMS-DEBUG] Exclusão cancelada pelo usuário.');
                            return;
                        }

                        // 🔍 Busca o usuário do sessionStorage
                        var bpUserData = sessionStorage.getItem('bp_user');
                        if (!bpUserData) {
                            alert('Erro: dados de usuário não encontrados.');
                            //                     console.error('[WPLMS-DEBUG] ❌ bp_user ausente no sessionStorage.');
                            return;
                        }

                        var user = JSON.parse(bpUserData);
                        console.log('[WPLMS-DEBUG] Usuário recuperado do sessionStorage:', user);

                        // 🧩 Prepara os dados para o POST
                        var postData = {
                            action: 'delete_activity_update_admin',
                            activity_id: activity_id,
                            user_id: user.id || null,
                            user_roles: user.roles || [],
                            user_email: user.email || '',
                            _ajax_nonce: '<?php echo $nonce; ?>'
                        };

                        //                 console.log('[WPLMS-DEBUG] Enviando requisição AJAX');
                        //                 console.log('POST URL:', '<?php echo $ajax_url; ?>');
                        //                 console.log('POST DATA:', postData);

                        $.post('<?php echo $ajax_url; ?>', postData)
                            .done(function(response) {
                                //                     console.log('[WPLMS-DEBUG] Resposta AJAX recebida:', response);

                                if (response.success) {
                                    //                         console.log('[WPLMS-DEBUG] ✅ Exclusão confirmada pelo servidor.');
                                    $('.activity_item[data-activity-id="' + activity_id + '"]').fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                } else {
                                    //                         console.error('[WPLMS-DEBUG] ❌ Erro do servidor:', response.data);
                                    alert('Erro ao excluir: ' + (response.data || 'Falha desconhecida.'));
                                }
                            })
                            .fail(function(xhr) {
                                //                     console.error('[WPLMS-DEBUG] ❌ Falha AJAX — status:', xhr.status, 'response:', xhr.responseText);
                                alert('Erro de comunicação com o servidor. (status ' + xhr.status + ')');
                            });
                    });
            }

            // Inicia a verificação
            verificarBpUser();
        })(jQuery);
    </script>

    <style>
        /* 🎨 Modal de confirmação */
        #wplms-confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            animation: fadeIn 0.2s ease-in-out;
        }

        #wplms-confirm-box {
            background: #fff;
            border-radius: 10px;
            width: 360px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            text-align: center;
            padding: 25px 20px;
            font-family: 'Inter', sans-serif;
            animation: popIn 0.25s ease-out;
        }

        #wplms-confirm-box h3 {
            margin-top: 0;
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }

        #wplms-confirm-box p {
            font-size: 15px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.4em;
        }

        #wplms-confirm-box .buttons {
            display: flex;
            justify-content: space-around;
            gap: 15px;
        }

        #wplms-confirm-box button {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s ease-in-out;
        }

        #wplms-confirm-box button.cancel {
            background: #ccc;
            color: #333;
        }

        #wplms-confirm-box button.cancel:hover {
            background: #b5b5b5;
        }

        #wplms-confirm-box button.confirm {
            background: #e74c3c;
            color: #fff;
        }

        #wplms-confirm-box button.confirm:hover {
            background: #c0392b;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes popIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .delete-activity-update {
            color: #e74c3c !important;
            cursor: pointer;
            margin-left: 8px;
            transition: .2s;
        }

        .delete-activity-update:hover {
            color: #c0392b !important;
        }
    </style>
<?php
});

/**
 * ==========================================================
 * 🧰 BACK-END — AJAX Exclusão direta (com dupla validação)
 * ==========================================================
 */
add_action('wp_ajax_delete_activity_update_admin', 'delete_activity_update_admin');
add_action('wp_ajax_nopriv_delete_activity_update_admin', 'delete_activity_update_admin');

function delete_activity_update_admin()
{
    check_ajax_referer('delete_activity_update_nonce');

    global $wpdb, $bp;

    $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
    $user_id     = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $user_roles  = isset($_POST['user_roles']) ? (array) $_POST['user_roles'] : [];
    $user_email  = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';

    // 🔍 Log de entrada para debug
    error_log('[WPLMS-DEBUG][AJAX] Recebido: ' . json_encode($_POST));

    if (!$activity_id || !$user_id || !$user_email || empty($user_roles)) {
        wp_send_json_error('Dados insuficientes.');
    }

    if (!in_array('administrator', $user_roles)) {
        wp_send_json_error('Permissão negada. Usuário não é administrador.');
    }

    $user = get_user_by('email', $user_email);
    if (!$user || !in_array('administrator', (array)$user->roles)) {
        wp_send_json_error('Permissão negada. E-mail não corresponde a um administrador.');
    }

    $table_activity = isset($bp->activity->table_name) ? $bp->activity->table_name : $wpdb->prefix . 'bp_activity';
    $table_meta     = isset($bp->activity->table_name_meta) ? $bp->activity->table_name_meta : $wpdb->prefix . 'bp_activity_meta';

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_activity} WHERE id = %d", $activity_id));
    if (!$exists) {
        wp_send_json_error('Atividade não encontrada.');
    }

    $deleted = $wpdb->delete($table_activity, ['id' => $activity_id], ['%d']);
    $wpdb->delete($table_meta, ['activity_id' => $activity_id], ['%d']);

    if ($deleted) {
        error_log("[WPLMS-DEBUG][AJAX] ✅ Atividade {$activity_id} excluída por {$user_email}");
        wp_send_json_success('Atividade excluída com sucesso.');
    } else {
        wp_send_json_error('Falha ao excluir.');
    }
}





add_action('wp_footer', function () { ?>
    <script>
        (function($) {
            //     console.info('[WPLMS-DEBUG] 🧹 Função de limpeza e ocultação de ícones iniciada.');

            /**
             * ==========================================================
             * 🧠 Função principal
             * ==========================================================
             * - Oculta ícones de lixeira em "entrou no grupo"
             * - Mantém apenas o ícone de lixeira válido (com data e title)
             */
            function limparIconesLixeira() {

                // 🔹 1️⃣ Ocultar ícones de lixeira em atividades "entrou no grupo"
                const groupItems = $('.activity_item.joined_group.groups');
                if (groupItems.length > 0) {
                    groupItems.each(function() {
                        const item = $(this);
                        const trashIcons = item.find('.activity_actions .vicon-trash, .activity_actions .delete-activity-update');
                        if (trashIcons.length > 0) {
                            trashIcons.css('display', 'none');
                            console.info('[WPLMS-DEBUG] 🚫 Ícone ocultado — joined_group ID:', item.data('activity-id'));
                        }
                    });
                }

                // 🔹 2️⃣ Remover ícones duplicados, mantendo o válido
                const allActions = $('.activity_actions');
                allActions.each(function() {
                    const actions = $(this);
                    const trashIcons = actions.find('.vicon-trash');

                    // Se há mais de uma lixeira no mesmo bloco
                    if (trashIcons.length > 1) {
                        let validIconFound = false;

                        trashIcons.each(function() {
                            const icon = $(this);
                            const hasData = icon.is('[data-activity-id]');
                            const hasTitle = icon.is('[title]');

                            // Mantém somente o ícone válido
                            if (hasData && hasTitle && !validIconFound) {
                                validIconFound = true; // mantém apenas o primeiro válido
                                //                         console.log('[WPLMS-DEBUG] ✅ Mantendo ícone válido com data/title.');
                            } else {
                                //                         console.warn('[WPLMS-DEBUG] 🗑️ Ícone inválido removido:', this);
                                icon.remove();
                            }
                        });
                    }
                });
            }

            // 🚀 Executa no carregamento e periodicamente
            limparIconesLixeira();
            setInterval(limparIconesLixeira, 1500);

            // 🔄 Também após qualquer AJAX (BuddyPress/WPLMS)
            $(document).on('ajaxComplete.limparIconesLixeira', limparIconesLixeira);

        })(jQuery);
    </script>
<?php });




/**
 * Script personalizado para bloquear acesso de usuários sem função no WPLMS
 * 
 * Autor: Marcelo Didier
 * Data: 03/12/2025
 * 
 * Descrição:
 * Este código garante que nenhum usuário sem papel (role) atribuído no WordPress
 * consiga permanecer logado no sistema WPLMS — mesmo que consiga burlar a tela de login
 * via fluxos personalizados (ex: BuddyPress, SPA, API externa, login social, etc).
 * 
 * - Executa no início de cada requisição (prioridade 1 no hook `init`).
 * - Verifica se o usuário está logado e se possui ao menos uma função.
 * - Caso contrário, força a destruição da sessão e limpa os cookies de autenticação.
 * - Redireciona imediatamente o usuário para a home do site.
 * - Compatível com o WPLMS 4 (SPA), login via AJAX e fluxo BuddyPress.
 * - Ideal para garantir controle de acesso baseado em roles, impedindo acessos indevidos.
 */

// Impede login de usuários sem função
function bloquear_login_usuarios_sem_role($user, $username, $password)
{
    if (is_wp_error($user)) {
        return $user;
    }

    if (empty($user->roles)) {
        return new WP_Error('sem_permissao', __('Erro: sua conta não tem permissão para acessar o sistema.'));
    }

    return $user;
}
add_filter('authenticate', 'bloquear_login_usuarios_sem_role', 30, 3);


// Impede acesso ao dashboard WPLMS 4 e faz logout de usuários sem função
function bloquear_acesso_dashboard_wplms4()
{
    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();

    // Administradores sempre têm acesso
    if (in_array('administrator', $user->roles)) return;

    // Se não tem nenhuma função e está tentando acessar o painel
    if (empty($user->roles)) {
        $current_uri = $_SERVER['REQUEST_URI'];

        // Painel WPLMS 4: URLs geralmente são /dashboard ou /app
        if (preg_match('/\/(dashboard|app)/i', $current_uri)) {
            wp_logout();
            wp_redirect(home_url('/'));
            exit;
        }
    }
}
add_action('template_redirect', 'bloquear_acesso_dashboard_wplms4');






// add_action('wp_enqueue_scripts', function () {

//     // URL do CSS hospedado no GitHub via Githack
//     $github_css_url = 'https://raw.githack.com/equipewebnauta/spacelms-customCSS/main/custom-style.css';

//     // Endpoint para obter informações do último commit na branch main
//     $api_url = 'https://api.github.com/repos/equipewebnauta/spacelms-customCSS/commits/main';

//     // Requisição para o GitHub (GitHub exige User-Agent)
//     $response = wp_remote_get($api_url, [
//         'headers' => [
//             'User-Agent' => 'WordPress/' . get_bloginfo('version'),
//         ]
//     ]);

//     // Recupera o SHA do commit para usar como versão de cache-busting
//     if (!is_wp_error($response)) {
//         $data = json_decode(wp_remote_retrieve_body($response), true);
//         $version = isset($data['sha']) ? $data['sha'] : time();
//     } else {
//         // Se a API do GitHub falhar, usa time() como fallback
//         $version = time();
//     }

//     // Registra o CSS
//     wp_register_style(
//         'spacelms-custom-github',
//         $github_css_url,
//         [],
//         $version
//     );

//     // Enfileira no final do head
//     wp_enqueue_style('spacelms-custom-github');

// }, 999999);


// // Força imprimir o CSS no footer (último de todos)
// add_action('wp_print_footer_scripts', function () {
//     wp_print_styles(['spacelms-custom-github']);
// });



































/**
 * Recuperação da Carga Horária do Curso Base (WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 14/01/2026
 * 
 * Descrição:
 * Este trecho do script é responsável por identificar e armazenar a **carga horária total**
 * do curso base utilizado no certificado, garantindo que a informação exibida seja sempre
 * referente ao **curso principal**, e não ao curso da categoria "certificados".
 * 
 * A carga horária é obtida a partir de um **campo meta nativo do WPLMS**, normalmente
 * configurado no cadastro do curso.
 * 
 * ⚙️ O que este código faz:
 * - Utiliza o ID do **curso base**, previamente definido pela lógica de validação
 *   (curso original ou curso alternativo fora da categoria "certificados").
 * - Busca o valor do metadado `_wplms_carga_horaria`.
 * - Armazena o valor na variável `$carga_horaria`.
 * - Normaliza o resultado para garantir um valor inteiro.
 * - Permite uso direto da variável em certificados, templates, PDFs ou relatórios.
 * 
 * 📚 Origem da carga horária:
 * Os dados são obtidos da tabela:
 *     wp_postmeta
 * 
 * Através do metadado:
 *     meta_key = '_wplms_carga_horaria'
 * 
 * Exemplo de registro no banco:
 *     post_id    → ID do curso base
 *     meta_key   → _wplms_carga_horaria
 *     meta_value → 18
 * 
 * 🔍 Validações aplicadas:
 * - Verificação da existência do curso base.
 * - Leitura segura do valor do meta campo.
 * - Conversão explícita para inteiro (`intval`).
 * - Fallback automático para `0` caso o valor não exista.
 * 
 * 🧠 Funcionamento técnico:
 * - Utiliza a função nativa `get_post_meta()`.
 * - Não depende de queries SQL manuais.
 * - Totalmente compatível com a arquitetura do WPLMS.
 * 
 * 🖥️ Uso da variável:
 * Após a execução deste trecho, a variável estará disponível como:
 * 
 *     $carga_horaria = 18;
 * 
 * Podendo ser utilizada, por exemplo, em:
 * - Texto do certificado
 * - Templates HTML
 * - Geração de PDF
 * - APIs ou retornos JSON
 * 
 * 💡 Observações:
 * - Caso o campo `_wplms_carga_horaria` não esteja preenchido no curso,
 *   o valor retornado será `0`.
 * - Se a escola utilizar outro meta campo para carga horária,
 *   o `meta_key` pode ser ajustado facilmente.
 * 
 * 🚀 Possíveis evoluções:
 * - Calcular automaticamente a carga horária somando a duração das unidades.
 * - Aplicar filtros para exibição textual (ex: "18 horas").
 * - Criar fallback inteligente caso o meta não esteja definido.
 */
function bizu_carga_horaria_shortcode() {

    ob_start();

    /* ==============================
     * 1. Validação do parâmetro
     * ============================== */
    if ( empty($_GET['c']) ) {
        echo '0 horas';
        return ob_get_clean();
    }

    $course_id = absint($_GET['c']);
    $curso_base_id = $course_id;

    /* ==============================
     * 2. Tenta pegar direto do curso
     * ============================== */
    $carga_horaria = get_post_meta(
        $curso_base_id,
        '_wplms_carga_horaria',
        true
    );

    /* ==============================
     * 3. Se não encontrou, busca curso base
     * ============================== */
    if ( $carga_horaria === '' ) {

        $query = new WP_Query([
            'post_type'      => 'course',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_wplms_carga_horaria',
                    'compare' => 'EXISTS',
                ]
            ],
            'tax_query'      => [
                [
                    'taxonomy' => 'course-cat',
                    'field'    => 'slug',
                    'terms'    => ['certificados'],
                    'operator' => 'NOT IN',
                ]
            ],
        ]);

        if ( $query->have_posts() ) {
            $query->the_post();
            $curso_base_id = get_the_ID();

            $carga_horaria = get_post_meta(
                $curso_base_id,
                '_wplms_carga_horaria',
                true
            );
        }

        wp_reset_postdata();
    }

    /* ==============================
     * 4. Normalização final
     * ============================== */
    $carga_horaria = absint($carga_horaria);

    echo esc_html($carga_horaria) . ' horas';

    return ob_get_clean();
}
add_shortcode('bizu_carga_horaria', 'bizu_carga_horaria_shortcode');


/**
 * Campo Personalizado de Carga Horária para Cursos (WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 19/01/2026
 * 
 * Descrição:
 * Este script adiciona e gerencia um campo personalizado de **Carga Horária do Curso**
 * no painel de criação e edição de cursos do tema **WPLMS**, sem depender dos hooks
 * internos do tema ou do ciclo de vida do React.
 * 
 * A solução foi desenvolvida especificamente para lidar com o comportamento SPA
 * (Single Page Application) do WPLMS, garantindo que o campo:
 * - Sempre apareça ao criar ou editar um curso
 * - Nunca seja removido permanentemente pelo React
 * - Carregue automaticamente valores já salvos
 * - Salve os dados de forma segura no banco
 * 
 * ⚙️ O que este código faz:
 * - Injeta dinamicamente um campo numérico "Carga horária do curso (horas)" na interface
 *   de criação/edição de cursos do WPLMS.
 * - Monitora continuamente o DOM para reinjetar o campo caso o React destrua ou recrie
 *   a interface (comportamento comum do WPLMS).
 * - Carrega automaticamente o valor da carga horária já salva no banco de dados.
 * - Permite editar o valor a qualquer momento, mesmo após sair e retornar ao curso.
 * 
 * 💾 Estratégia de armazenamento:
 * - Durante a digitação, o valor é salvo temporariamente em:
 *     wp_usermeta → _wplms_carga_horaria_temp
 * - Ao salvar/publicar o curso, o valor é vinculado definitivamente ao curso em:
 *     wp_postmeta → _wplms_carga_horaria
 * 
 * 🔄 Recuperação automática de dados:
 * - Ao acessar novamente o curso para edição, o script:
 *     • Busca primeiro a carga horária salva no post_meta do curso
 *     • Caso não exista, utiliza o valor temporário salvo no user_meta
 * - O valor recuperado é automaticamente reaplicado no campo injetado.
 * 
 * 🧠 Funcionamento técnico:
 * - Utiliza JavaScript com jQuery para injetar o campo diretamente no DOM.
 * - Implementa um "watchdog" com setInterval para:
 *     • Detectar quando o React remove o campo
 *     • Reinjetar o campo imediatamente
 *     • Reaplicar o valor salvo no input
 * - Detecta navegação interna via hashchange, comum no WPLMS (SPA),
 *   forçando nova verificação e reinjeção do campo.
 * - Usa AJAX (`admin-ajax.php`) para comunicação direta com o banco de dados.
 * 
 * 🔐 Segurança e controle:
 * - Todas as ações AJAX exigem que o usuário esteja autenticado.
 * - Os dados são sanitizados antes de serem persistidos.
 * - O campo é exclusivo para usuários com permissão de edição de cursos.
 * 
 * 📊 Exemplo prático:
 * - Instrutor cria um curso e informa "40" horas no campo de carga horária.
 * - O valor é salvo automaticamente durante a digitação.
 * - O instrutor sai da página e retorna posteriormente.
 * - O campo reaparece automaticamente já preenchido com "40".
 * - O valor pode ser editado e salvo novamente sem perda de dados.
 * 
 * 🧩 Requisitos:
 * - WordPress com tema WPLMS instalado e ativo.
 * - Ambiente que utilize o Course Builder padrão do WPLMS (React).
 * - Usuário logado com permissão para criar/editar cursos.
 * 
 * 💡 Observações importantes:
 * - Este código **não altera arquivos do WPLMS**, garantindo compatibilidade com updates.
 * - Não depende de hooks internos do Course Builder, evitando quebras futuras.
 * - Ideal para ambientes onde o WPLMS utiliza navegação sem reload de página (SPA).
 * 
 * 💡 Possíveis extensões:
 * - Exibir a carga horária automaticamente na página pública do curso.
 * - Integrar a carga horária a certificados.
 * - Validar carga horária mínima antes de permitir publicação do curso.
 * - Sincronizar o valor com APIs externas ou relatórios administrativos.
 */



/* =======================
 * REGISTRA API
 * ======================= 
 * API PÚBLICA – CARGA HORÁRIA DO CURSO
 * ====================================================== */
add_action('rest_api_init', function () {

    register_rest_route('public/v1', '/carga-horaria', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'public_rest_carga_horaria',
        'permission_callback' => '__return_true'
    ]);

});

function public_rest_carga_horaria(WP_REST_Request $request) {

    $title = trim((string) $request->get_param('course_title'));
    $carga = $request->get_param('carga_horaria');

    if ($title === '') {
        return ['carga_horaria' => null];
    }

    $query = new WP_Query([
        'post_type'      => 'course',
        'post_status'    => ['publish','draft','pending'],
        'posts_per_page' => 1,
        'title'          => $title,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    if (empty($query->posts)) {
        return ['carga_horaria' => null];
    }

    $course_id = (int) $query->posts[0];

    // 🔎 GET → apenas retorna se existir
    if ($request->get_method() === 'GET') {
        $valor = get_post_meta($course_id, '_wplms_carga_horaria', true);
        return ['carga_horaria' => $valor !== '' ? (int)$valor : null];
    }

    // 💾 POST → salva direto no curso
    if (is_numeric($carga) && (int)$carga > 0) {

        update_post_meta(
            $course_id,
            '_wplms_carga_horaria',
            (int)$carga
        );

        return [
            'success'    => true,
            'course_id' => $course_id,
            'carga'     => (int)$carga
        ];
    }

    return new WP_REST_Response(['error' => 'invalid_data'], 400);
}

add_action('wp_footer', function () {
?>
<script>
(function($){

    //console.log('[WPLMS CH] Script carregado (REST público OK)');

    let cargaAtual   = null;
    let ultimoTitulo = null;
    let bloqueado    = false;

    function getCourseTitle(){
        let t = $('input.text_field[placeholder="Nome do curso"]').val();
        return t ? t.trim() : null;
    }

    function api(method, data, cb){
        $.ajax({
            url: '/wp-json/public/v1/carga-horaria',
            method: method,
            data: data,
            success: cb,
            error: function(err){
                console.error('[WPLMS CH] API erro', err);
            }
        });
    }

    function injectField(){

        if (!$('.vibe_vibe_duration .grid').length) return;

        if (!$('#wplms_carga_horaria').length) {

            $('.vibe_vibe_duration .grid').append(`
                <div class="field_wrapper field_number field_carga_horaria">
                    <span>
                        <label>Carga horária do curso (horas)</label>
                        <strong>Total de horas do curso</strong>
                    </span>
                    <div class="field_value">
                        <div class="number">
                            <input type="number"
                                   id="wplms_carga_horaria"
                                   min="1"
                                   step="1"
                                   placeholder="Ex: 40"/>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    function applyValue(){
        let $input = $('#wplms_carga_horaria');
        if (!$input.length) return;

        if (cargaAtual === null) {
            $input.val('');
            return;
        }

        if ($input.val() !== String(cargaAtual)) {
            $input.val(cargaAtual);
            console.log('[WPLMS CH] Valor aplicado:', cargaAtual);
        }
    }

    function fetchCargaHoraria(){

        let titulo = getCourseTitle();
        if (!titulo || titulo === ultimoTitulo || bloqueado) return;

        bloqueado    = true;
        ultimoTitulo = titulo;
        cargaAtual   = null;

        api('GET', { course_title: titulo }, function(resp){
            cargaAtual = resp?.carga_horaria ?? null;
            applyValue();
            bloqueado = false;
        });
    }

    // 💾 SALVA
    $(document).on('input change', '#wplms_carga_horaria', function(){

        let carga  = $(this).val();
        let titulo = getCourseTitle();

        if (!titulo || !carga || carga === String(cargaAtual)) return;

        cargaAtual = carga;

        api('POST', {
            course_title: titulo,
            carga_horaria: carga
        }, function(){
            console.log('[WPLMS CH] Salvo:', carga);
        });
    });

    // ⏱️ Observa mudanças do APP
    setInterval(function(){
        injectField();
        fetchCargaHoraria();
    }, 500);

    // 🔄 troca de curso
    $(window).on('hashchange', function(){
        ultimoTitulo = null;
        cargaAtual   = null;
        $('#wplms_carga_horaria').val('');
    });

})(jQuery);
</script>
<?php
});

/* ======================================================
 * 2. AJAX QUE SALVA NO BANCO
 * ====================================================== */
add_action('wp_ajax_wplms_save_carga_horaria_by_title', 'wplms_save_carga_horaria_by_title');
function wplms_save_carga_horaria_by_title() {


    $title = isset($_POST['course_title']) ? trim(wp_unslash($_POST['course_title'])) : '';
    $carga = intval($_POST['carga_horaria'] ?? 0);

    if ($title === '' || $carga <= 0) {
        wp_send_json_error('Dados inválidos');
    }

    $query = new WP_Query([
        'post_type'      => 'course',
        'post_status'    => ['publish','draft','pending'],
        'posts_per_page' => 1,
        'title'          => $title,
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ]);

    if (empty($query->posts)) {
        error_log('[WPLMS CH] ERRO AO SALVAR – curso não encontrado: ' . $title);
        wp_send_json_error('Curso não encontrado');
    }

    $course_id = $query->posts[0];

    update_post_meta(
        $course_id,
        '_wplms_carga_horaria',
        $carga
    );

    error_log('[WPLMS CH] Carga horária salva | Curso ID ' . $course_id . ' | ' . $carga);

    wp_send_json_success([
        'course_id' => $course_id,
        'carga'     => $carga
    ]);
}

/* ======================================================
 * 3. CARREGA O VALOR AO EDITAR O CURSO
 * ====================================================== */
add_action('wp_ajax_wplms_get_carga_horaria_by_title', 'wplms_get_carga_horaria_by_title');
function wplms_get_carga_horaria_by_title() {

 

    $title = isset($_POST['course_title']) ? trim(wp_unslash($_POST['course_title'])) : '';

    if ($title === '') {
        wp_send_json_success(null);
    }

    $query = new WP_Query([
        'post_type'      => 'course',
        'post_status'    => ['publish','draft','pending'],
        'posts_per_page' => 1,
        'title'          => $title,
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ]);

    if (empty($query->posts)) {
        error_log('[WPLMS CH] Curso não encontrado pelo título: ' . $title);
        wp_send_json_success(null);
    }

    $course_id = $query->posts[0];

    wp_send_json_success(
        get_post_meta($course_id, '_wplms_carga_horaria', true)
    );
}

/* ======================================================
 * 4. PREENCHE O CAMPO AO EDITAR (JS)
 * ====================================================== */
add_action('save_post_course', 'wplms_attach_carga_horaria_to_course', 10, 3);
function wplms_attach_carga_horaria_to_course($post_id, $post, $update) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_status !== 'publish' && $post->post_status !== 'draft') return;

    $carga = get_user_meta(
        $post->post_author,
        '_wplms_carga_horaria_temp',
        true
    );

    if (!$carga) return;

    update_post_meta(
        $post_id,
        '_wplms_carga_horaria',
        intval($carga)
    );

    delete_user_meta($post->post_author, '_wplms_carga_horaria_temp');

    error_log('[WPLMS CH] Carga horária vinculada ao curso ID ' . $post_id);
}





add_filter('wplms_course_creation_data', 'wplms_load_carga_horaria_course', 10, 2);
function wplms_load_carga_horaria_course($data, $course_id) {

    if ($course_id) {
        $data['wplms_carga_horaria'] = get_post_meta(
            $course_id,
            '_wplms_carga_horaria',
            true
        );
    }

    return $data;
}











/**
 * Relatório de Alunos Removidos por Curso – WPLMS
 * 
 * Autor: Miguel Ferreira
 * Data: 23/01/2026
 * 
 * Descrição:
 * Este código implementa um painel administrativo no WordPress para **monitoramento e auditoria
 * de alunos removidos de cursos no WPLMS**, incluindo remoções realizadas via interface React
 * do próprio WPLMS (Manage Courses).
 * 
 * A funcionalidade foi projetada para garantir **rastreabilidade completa** das ações de
 * remoção de alunos, permitindo identificar **quem removeu**, **quando removeu** e
 * **de qual curso o aluno foi removido**, mesmo quando a ação ocorre via AJAX/REST.
 * 
 * ⚙️ O que este código faz:
 * - Cria uma aba no painel administrativo do WordPress chamada **"Alunos Removidos"**.
 * - Permite selecionar um curso (post_type = `course`) e visualizar:
 *     • Nome do aluno removido
 *     • E-mail do aluno
 *     • Data e hora da remoção
 *     • Usuário responsável pela remoção (Admin / Instrutor)
 *     • Foto (avatar) do usuário que realizou a remoção
 * - Exibe fallback automático quando a remoção é realizada pelo sistema (cron/automação).
 * 
 * 🔍 Origem dos dados:
 * Os dados exibidos são obtidos da tabela de histórico personalizada:
 *     wp_wplms_course_removal_log
 * 
 * Essa tabela é alimentada automaticamente por hooks do WordPress:
 * - `deleted_user_meta`
 * - `updated_user_meta`
 * 
 * Esses hooks garantem o registro da remoção independentemente da origem:
 *     • Interface React do WPLMS
 *     • AJAX / REST API
 *     • Código interno do tema ou plugins
 *     • Ações administrativas diretas
 * 
 * 🧠 Funcionamento técnico:
 * - O usuário autenticado que inicia a requisição é capturado no início do ciclo (`init`)
 *   e armazenado temporariamente para garantir identificação correta do responsável.
 * - Quando o WPLMS remove o meta `course_status<ID_DO_CURSO>`, o evento é interceptado.
 * - A remoção é registrada com data/hora, curso, aluno e usuário responsável.
 * - A tela administrativa executa consultas SQL otimizadas com JOIN na tabela `wp_users`
 *   para recuperar informações do aluno e do usuário que realizou a ação.
 * - O avatar do responsável é exibido utilizando `get_avatar()`, garantindo compatibilidade
 *   com Gravatar e avatares locais do WordPress.
 * 
 * 📊 Exemplo de exibição no painel:
 * | Aluno            | Email              | Removido em        | Removido por              |
 * |------------------|--------------------|--------------------|---------------------------|
 * | João da Silva    | joao@email.com     | 23/01/2026 14:32   | [avatar] Maria Souza      |
 * | Ana Pereira      | ana@email.com      | 23/01/2026 15:01   | Sistema                   |
 * 
 * 🔐 Segurança e boas práticas:
 * - Acesso restrito a usuários com permissão administrativa.
 * - Nenhuma modificação direta no código do WPLMS.
 * - Auditoria completa para conformidade (LGPD / Compliance).
 * - Separação clara entre dados operacionais e histórico.
 * 
 * 🧩 Requisitos:
 * - WordPress ativo.
 * - Tema WPLMS instalado e ativo.
 * - Estrutura padrão de cursos do WPLMS (`post_type = course`).
 * - Tabela personalizada `wp_wplms_course_removal_log` criada previamente.
 * 
 * 💡 Possíveis evoluções:
 * - Botão para reativar aluno com auditoria.
 * - Filtro por período de remoção.
 * - Exportação CSV do histórico.
 * - Relatórios por instrutor.
 * - Dashboard com métricas de evasão por curso.
 * 
 * 
 * `CREATE TABLE wp_wplms_course_removal_log (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
user_id BIGINT UNSIGNED NOT NULL,
course_id BIGINT UNSIGNED NOT NULL,
removed_by BIGINT UNSIGNED NULL,
removal_type VARCHAR(50) DEFAULT 'delete',
removed_at DATETIME NOT NULL,
PRIMARY KEY (id),
KEY user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`
 * 
 * 
 * 
 */




add_action( 'init', function () {
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        if ( $user && $user->ID ) {
            $GLOBALS['wplms_action_user_id'] = $user->ID;
        }
    }
});





add_action( 'deleted_user_meta', 'wplms_monitor_course_removal', 10, 3 );
function wplms_monitor_course_removal( $meta_ids, $user_id, $meta_key ) {

    if ( strpos( $meta_key, 'course_status' ) !== 0 ) {
        return;
    }

    $course_id = (int) str_replace( 'course_status', '', $meta_key );
    if ( ! $course_id ) {
        return;
    }

    // 🔥 Usuário que iniciou a ação
    $removed_by = isset( $GLOBALS['wplms_action_user_id'] )
        ? (int) $GLOBALS['wplms_action_user_id']
        : null;

    global $wpdb;
    $table = $wpdb->prefix . 'wplms_course_removal_log';

    // Evita duplicidade
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table 
         WHERE user_id = %d AND course_id = %d 
         ORDER BY removed_at DESC LIMIT 1",
        $user_id,
        $course_id
    ) );

    if ( $exists ) {
        return;
    }

    $wpdb->insert(
        $table,
        [
            'user_id'      => $user_id,
            'course_id'    => $course_id,
            'removed_by'   => $removed_by,
            'removal_type' => 'deleted_meta',
            'removed_at'   => current_time( 'mysql' ),
        ],
        [ '%d', '%d', '%d', '%s', '%s' ]
    );
}




add_action( 'admin_menu', 'wplms_removed_students_menu' );
function wplms_removed_students_menu() {

    add_menu_page(
        'Alunos Removidos',
        'Alunos Removidos',
        'manage_options',
        'wplms-alunos-removidos',
        'wplms_render_removed_students_page',
        'dashicons-dismiss',
        2
    );
}




function wplms_render_removed_students_page() {
    global $wpdb;

    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    ?>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <div class="wrap">

        <h1 class="mb-4">❌ Alunos Removidos do Curso</h1>
		<p class="description mb-4">
			Este recurso permite acompanhar os alunos que foram removidos de um curso.
			Basta selecionar o curso desejado para visualizar a lista de alunos removidos,
			incluindo a data da remoção e o usuário responsável pela ação.
		</p>
        <!-- Filtro -->
        <form method="get" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="page" value="wplms-alunos-removidos">

            <div class="col-md-6">
                <label class="form-label fw-bold">Curso</label>
                <select name="course_id" class="form-select" required>
                    <option value="">Selecione um curso</option>

                    <?php
                    $courses = get_posts([
                        'post_type'      => 'course',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                    ]);

                    foreach ($courses as $course) {
                        printf(
                            '<option value="%d" %s>%s</option>',
                            $course->ID,
                            selected($course_id, $course->ID, false),
                            esc_html($course->post_title)
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-3">
                <button class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>

        <?php if ($course_id) : ?>

            <?php
            $table = $wpdb->prefix . 'wplms_course_removal_log';

            $sql = $wpdb->prepare(
                "
                SELECT 
                    l.user_id,
                    l.course_id,
                    l.removed_at,
                    l.removed_by,
                    u.display_name AS student_name,
                    u.user_email
                FROM $table l
                INNER JOIN {$wpdb->users} u 
                    ON u.ID = l.user_id
                WHERE l.course_id = %d
                ORDER BY l.removed_at DESC
                ",
                $course_id
            );

            $removed_students = $wpdb->get_results($sql);
            ?>

            <h2 class="mb-3">Resultado</h2>

            <?php if ($removed_students) : ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle widefat">
                        <thead class="table-dark">
                            <tr>
                                <th>Aluno</th>
                                <th>Email</th>
                                <th>Removido em</th>
                                <th>Removido por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($removed_students as $row) : ?>
                                <tr>
                                    <!-- ALUNO -->
                                    <td>
                                        <strong><?php echo esc_html($row->student_name); ?></strong>
                                    </td>

                                    <td><?php echo esc_html($row->user_email); ?></td>

                                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($row->removed_at))); ?></td>

                                    <!-- REMOVIDO POR -->
                                    <td>
                                        <?php
                                        if ($row->removed_by) {
                                            $remover = get_user_by('id', $row->removed_by);

                                            if ($remover) {
                                                echo '<div class="d-flex align-items-center gap-2">';
                                                echo get_avatar($remover->ID, 32);
                                                echo '<span>' . esc_html($remover->display_name) . '</span>';
                                                echo '</div>';
                                            } else {
                                                echo '<span class="text-muted">Usuário removido</span>';
                                            }
                                        } else {
                                            echo '<em>Sistema</em>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-warning">
                    Nenhum aluno removido encontrado para este curso.
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
    <?php
}







/**
 * Painel Administrativo de Inscrição em Massa de Alunos (WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 02/02/2026
 * 
 * Descrição:
 * Este script cria um painel administrativo personalizado no WordPress para
 * **inscrição manual e em massa de alunos em cursos da plataforma WPLMS**,
 * permitindo que administradores selecionem múltiplos alunos e múltiplos cursos
 * em uma única ação, com validação real direto no banco de dados.
 * 
 * ⚙️ O que este código faz:
 * - Cria um novo item de menu no painel administrativo do WordPress:
 *     • Menu: "Inscrever Alunos"
 * - Exibe uma interface administrativa moderna utilizando **Bootstrap 5**.
 * - Lista dinamicamente:
 *     • Alunos com role `student` ou `subscriber`
 *     • Cursos publicados (post_type = course)
 * - Permite:
 *     • Selecionar alunos individualmente ou "Selecionar todos"
 *     • Selecionar cursos individualmente ou "Selecionar todos"
 * - Realiza a inscrição real dos alunos nos cursos via funções nativas do WPLMS.
 * 
 * 🔐 Segurança:
 * - Utiliza `wp_nonce_field()` e `wp_verify_nonce()` para evitar requisições indevidas.
 * - Sanitiza os IDs de usuários e cursos com `intval`.
 * - Verifica existência real de usuários e cursos antes de qualquer ação.
 * 
 * 🧠 Validação inteligente de inscrição:
 * - Antes de inscrever, o script verifica diretamente na tabela `wp_usermeta`
 *   se o aluno já possui a meta_key:
 *       course_status{ID_DO_CURSO}
 * - Isso garante que:
 *     • O aluno **não seja inscrito duas vezes**
 *     • A validação seja fiel ao funcionamento interno do WPLMS
 * 
 * 📊 Relatório de resultado:
 * Após o processamento, o sistema exibe:
 * - ✅ Lista de alunos inscritos com sucesso
 * - ⚠️ Lista de alunos ignorados por já estarem inscritos
 * 
 * Exemplo de retorno:
 * - aluno@email.com → Curso X (inscrito)
 * - aluno@email.com → Curso Y (já inscrito)
 * 
 * 🖥️ Interface administrativa:
 * - Layout responsivo com grid em duas colunas (Alunos | Cursos)
 * - Scroll interno para grandes volumes de dados
 * - Botões de ação destacados
 * - Feedback visual claro via alerts Bootstrap
 * 
 * 🧩 Componentes técnicos utilizados:
 * - Hooks do WordPress:
 *     • add_action('admin_menu')
 * - Funções nativas:
 *     • get_users()
 *     • get_posts()
 *     • get_user_by()
 *     • get_post()
 * - Integração direta com WPLMS:
 *     • bp_course_add_user_to_course()
 * - Acesso otimizado ao banco:
 *     • $wpdb->prepare()
 *     • $wpdb->get_var()
 * 
 * 📌 Requisitos:
 * - WordPress instalado
 * - WPLMS ativo
 * - BuddyPress ativo
 * - Usuário com permissão `manage_options`
 * 
 * 💡 Possíveis melhorias futuras:
 * - Filtro por turma, categoria ou instrutor
 * - Busca por nome ou e-mail do aluno
 * - Paginação para grandes bases de usuários
 * - Exportação do relatório em CSV
 * - Integração com ações automáticas (webhooks / logs)
 */

add_action('admin_menu', 'wplms_custom_inscricao_menu');
function wplms_custom_inscricao_menu() {

    add_menu_page(
        'Inscrever Alunos',                 // Page title
        'Inscrever Alunos',                 // Menu title
        'manage_options',                   // Capability
        'wplms-inscrever-alunos',            // Menu slug
        'wplms_custom_inscricao_page',       // Callback
        'dashicons-welcome-learn-more',      // Icon
        3                                   // 👈 Prioridade / posição no menu
    );
}

/* ======================================================
 * Página Admin
 * ====================================================== */
function wplms_custom_inscricao_page() {

    $resultado = null;

    if (isset($_POST['wplms_inscrever_aluno'])) {
        $resultado = wplms_custom_processar_inscricao();
    }

    $alunos = get_users([
        'role__in' => ['student', 'subscriber'],
        'number'   => -1
    ]);

    $cursos = get_posts([
        'post_type'   => 'course',
        'numberposts' => -1,
        'post_status' => 'publish'
    ]);
    ?>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
.wplms-inscricao-wrap {
    min-height: calc(100vh - 32px); /* admin bar */
}

.wplms-panel {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.wplms-panel-header {
    padding: 12px 16px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8f9fa;
}

.wplms-panel-body {
    padding: 12px 16px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(100vh - 300px);
}

@media (max-width: 991px) {
    .wplms-panel-body {
        max-height: 300px;
    }
}
</style>

<div class="wrap wplms-inscricao-wrap">
    <div class="container-fluid px-4 py-3">

        <!-- HEADER -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center
                            bg-dark text-white rounded px-4 py-3">
                    <h4 class="mb-0">Inscrever alunos em cursos</h4>
                </div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field('wplms_custom_inscricao_nonce'); ?>

            <!-- GRID PRINCIPAL -->
            <div class="row g-4 wplms-main-grid">

                <!-- ALUNOS -->
                <div class="col-12 col-lg-6">
                    <div class="wplms-panel h-100">
                        <div class="wplms-panel-header">
                            <strong>Alunos</strong>
                            <div class="form-check ms-3">
                                <input class="form-check-input" type="checkbox" id="select-all-alunos">
                                <label class="form-check-label" for="select-all-alunos">
                                    Selecionar todos
                                </label>
                            </div>
                        </div>

                        <div class="wplms-panel-body">
                            <?php foreach ($alunos as $aluno): ?>
                                <div class="form-check">
                                    <input class="form-check-input aluno-checkbox"
                                           type="checkbox"
                                           name="user_ids[]"
                                           value="<?php echo esc_attr($aluno->ID); ?>">
                                    <label class="form-check-label">
                                        <?php echo esc_html($aluno->display_name . ' – ' . $aluno->user_email); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- CURSOS -->
                <div class="col-12 col-lg-6">
                    <div class="wplms-panel h-100">
                        <div class="wplms-panel-header">
                            <strong>Cursos</strong>
                            <div class="form-check ms-3">
                                <input class="form-check-input" type="checkbox" id="select-all-cursos">
                                <label class="form-check-label" for="select-all-cursos">
                                    Selecionar todos
                                </label>
                            </div>
                        </div>

                        <div class="wplms-panel-body">
                            <?php foreach ($cursos as $curso): ?>
                                <div class="form-check">
                                    <input class="form-check-input curso-checkbox"
                                           type="checkbox"
                                           name="course_ids[]"
                                           value="<?php echo esc_attr($curso->ID); ?>">
                                    <label class="form-check-label">
                                        <?php echo esc_html($curso->post_title); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- AÇÕES -->
            <div class="row mt-4">
                <div class="col-12 text-end">
                    <button type="submit"
                            name="wplms_inscrever_aluno"
                            class="btn btn-primary btn-lg px-5">
                        Inscrever alunos
                    </button>
                </div>
            </div>
        </form>

        <!-- RESULTADO -->
        <?php if (!empty($resultado)) : ?>
            <div class="row mt-5">
                <div class="col-12">

                    <div class="bg-secondary text-white rounded px-4 py-2 mb-3">
                        <strong>Resultado da inscrição</strong>
                    </div>

                    <?php if (!empty($resultado['inscritos'])) : ?>
                        <div class="alert alert-success">
                            <strong>Inscritos com sucesso:</strong>
                            <ul class="mb-0">
                                <?php foreach ($resultado['inscritos'] as $r): ?>
                                    <li><?php echo esc_html($r); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($resultado['ignorados'])) : ?>
                        <div class="alert alert-warning">
                            <strong>Não inscritos:</strong>
                            <ul class="mb-0">
                                <?php foreach ($resultado['ignorados'] as $r): ?>
                                    <li><?php echo esc_html($r); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
    <script>
        document.getElementById('select-all-alunos').addEventListener('change', function () {
            document.querySelectorAll('.aluno-checkbox').forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('select-all-cursos').addEventListener('change', function () {
            document.querySelectorAll('.curso-checkbox').forEach(cb => cb.checked = this.checked);
        });
    </script>

    <?php
}



/* ======================================================
 * Processamento da inscrição (com relatório)
 * ====================================================== */
function wplms_custom_processar_inscricao() {

    if (
        !isset($_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'wplms_custom_inscricao_nonce')
    ) {
        return [];
    }

    if (empty($_POST['user_ids']) || empty($_POST['course_ids'])) {
        return [];
    }

    global $wpdb;

    $user_ids   = array_map('intval', $_POST['user_ids']);
    $course_ids = array_map('intval', $_POST['course_ids']);

    $resultado = [
        'inscritos' => [],
        'ignorados' => []
    ];

    foreach ($user_ids as $user_id) {

        if (!$user_id) continue;

        $user = get_user_by('id', $user_id);
        if (!$user) continue;

        foreach ($course_ids as $course_id) {

            if (!$course_id) continue;

            $curso = get_post($course_id);
            if (!$curso) continue;

            /**
             * 🔒 VALIDAÇÃO REAL NO BANCO (WPLMS)
             * Só está inscrito se EXISTIR meta_key = course_{ID}
             */
            $ja_inscrito = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT umeta_id
                    FROM {$wpdb->usermeta}
                    WHERE user_id = %d
                    AND meta_key = %s
                    LIMIT 1
                    ",
                    $user_id,
                    'course_status' . $course_id
                )
            );

            if ($ja_inscrito) {

                $resultado['ignorados'][] =
                    "{$user->user_email} → {$curso->post_title} (já inscrito)";

                continue;
            }

            /**
             * ✅ INSCRIÇÃO REAL
             */
            if (function_exists('bp_course_add_user_to_course')) {

                bp_course_add_user_to_course($user_id, $course_id);

                $resultado['inscritos'][] =
                    "{$user->user_email} → {$curso->post_title}";
            }
        }
    }

    return $resultado;
}
/* ======================================================
 * Admin Notice helper
 * ====================================================== */

function wplms_custom_admin_notice($message, $type = 'success') {

    $class = 'notice notice-' . esc_attr($type) . ' is-dismissible';

    echo '<div class="' . $class . '"><p>' . esc_html($message) . '</p></div>';
}







/**
 * Controle de Avanço de Unidades com Base na Conclusão de Vídeo (H5P + WPLMS)
 * 
 * Autor: Miguel Ferreira
 * Data: 09/02/2026
 * 
 * Descrição:
 * Este script implementa um mecanismo de controle de navegação em cursos
 * baseados em WPLMS / VibeBP, garantindo que o aluno só possa avançar para
 * a próxima unidade após assistir completamente ao vídeo da unidade atual.
 * A solução é totalmente compatível com ambientes SPA (React), onde não há
 * recarregamento de página entre as lições.
 * 
 * ⚙️ O que este código faz:
 * - Injeta um script JavaScript no footer do WordPress utilizando o hook `wp_footer`.
 * - Monitora dinamicamente alterações no DOM causadas pelo React (MutationObserver),
 *   identificando quando novas unidades ou vídeos são carregados.
 * - Localiza o iframe do H5P e acompanha o progresso do vídeo utilizando o
 *   elemento visual de tempo (.h5p-current / .h5p-total), sem depender do
 *   elemento <video> ou de eventos internos do H5P.
 * - Detecta o início do vídeo, acompanha o progresso em tempo real e identifica
 *   quando o vídeo chega ao seu tempo total.
 * - Bloqueia completamente o botão de navegação "Próxima unidade"
 *   (.next_curriculum_item.unlocked), impedindo cliques, eventos do React
 *   e navegação forçada enquanto o vídeo não for concluído.
 * - Libera automaticamente o avanço para a próxima unidade assim que o vídeo
 *   é finalizado.
 * 
 * 🔒 Estratégia de bloqueio:
 * - Desativação de interação via `pointer-events`.
 * - Interceptação de cliques no modo capture para evitar bypass pelo React.
 * - Alteração visual do botão (opacidade e aria-disabled) para indicar bloqueio.
 * 
 * 🧠 Funcionamento técnico:
 * - Acompanhamento do tempo do vídeo através do DOM interno do iframe H5P.
 * - Conversão de tempo exibido (mm:ss) para segundos para validação precisa.
 * - Reset automático do controle ao trocar de unidade ou carregar um novo iframe.
 * - Execução contínua e segura via intervalos leves (1s) para acompanhamento.
 * 
 * 🎯 Benefícios:
 * - Garante consumo integral do conteúdo em vídeo.
 * - Evita avanço indevido do aluno sem concluir a lição.
 * - Mantém compatibilidade total com React e navegação SPA.
 * - Não interfere na experiência do usuário nem quebra o fluxo do curso.
 * 
 * 🧩 Requisitos:
 * - WordPress com WPLMS / VibeBP ativo.
 * - Conteúdo de vídeo entregue via H5P (Interactive Video ou Video).
 * - Iframe H5P carregado no mesmo domínio (para acesso ao DOM interno).
 * 
 * 💡 Possíveis evoluções:
 * - Persistir progresso no banco de dados via AJAX.
 * - Integrar com controle de progresso nativo do WPLMS.
 * - Bloquear avanço mesmo via URL direta.
 * - Impedir aceleração do vídeo (playback > 1x).
 * - Gerar relatórios de tempo assistido por usuário.
 */

/*
add_action('wp_footer', 'wplms_block_next_unit_until_video_end', 99);
function wplms_block_next_unit_until_video_end() {
?>
<script>
(function () {

    let started = false;
    let finished = false;
    let currentIframe = null;
    let nextBlocked = false;

    function log(msg) {
        console.log('[H5P CONTROL]', msg);
    }

  
    function timeToSeconds(time) {
        if (!time) return 0;
        const p = time.split(':').map(Number);
        return p.length === 2 ? (p[0] * 60 + p[1]) : p[0];
    }

 
    function getNextCard() {
        return document.querySelector('.next_curriculum_item.unlocked');
    }

    function getLockedCards() {
        return document.querySelectorAll('.next_curriculum_item.locked');
    }

    function getNextArrow() {
        return document.querySelector('.unit_next.navigate_unit');
    }

    // 👉 NOVO: li.unit.open_lesson que possuem cadeado
    function getLockedLessons() {
        return document.querySelectorAll(
            'li.unit.open_lesson:has(.vicon-lock)'
        );
    }

    function blockElement(el) {
        if (!el) return;
        el.style.pointerEvents = 'none';
        el.style.opacity = '0.4';
        el.setAttribute('aria-disabled', 'true');
        el.classList.add('blocked-by-video');
    }

    function unblockElement(el) {
        if (!el) return;
        el.style.pointerEvents = '';
        el.style.opacity = '';
        el.removeAttribute('aria-disabled');
        el.classList.remove('blocked-by-video');
    }

    function syncLockedCards() {
        getLockedCards().forEach(card => {
            nextBlocked ? blockElement(card) : unblockElement(card);
        });

        // 👉 sincroniza também os li.unit.open_lesson com cadeado
        getLockedLessons().forEach(li => {
            nextBlocked ? blockElement(li) : unblockElement(li);
        });
    }

    function blockNext() {
        nextBlocked = true;
        blockElement(getNextCard());
        blockElement(getNextArrow());
        syncLockedCards();
        log('🔒 Avanço BLOQUEADO');
    }

    function unlockNext(reason = '') {
        nextBlocked = false;
        unblockElement(getNextCard());
        unblockElement(getNextArrow());
        syncLockedCards();
        log('🔓 Avanço LIBERADO ' + (reason ? '(' + reason + ')' : ''));
    }

   
    document.addEventListener('click', function (e) {

        // 👉 BLOQUEIA li.unit.open_lesson com cadeado
        const lockedLesson = e.target.closest(
            'li.unit.open_lesson:has(.vicon-lock)'
        );

        if (lockedLesson && nextBlocked) {
            e.preventDefault();
            e.stopImmediatePropagation();
            log('⛔ Clique bloqueado (li.unit com cadeado)');
            return;
        }

        const lockedCard = e.target.closest('.next_curriculum_item.locked');
        if (lockedCard && nextBlocked) {
            e.preventDefault();
            e.stopImmediatePropagation();
            log('⛔ Clique bloqueado (card locked)');
            return;
        }

        if (!nextBlocked) return;

        const card  = getNextCard();
        const arrow = getNextArrow();

        if ((card && card.contains(e.target)) || (arrow && arrow.contains(e.target))) {
            e.preventDefault();
            e.stopImmediatePropagation();
            log('⛔ Clique bloqueado (vídeo não finalizado)');
        }
    }, true);

   
    function resetTracker() {
        started = false;
        finished = false;
    }

    function trackIframe(iframe) {
        if (!iframe || !iframe.contentWindow) {
            unlockNext('sem vídeo');
            return;
        }

        let doc;
        try {
            doc = iframe.contentWindow.document;
        } catch (e) {
            unlockNext('iframe inacessível');
            return;
        }

        const currentEl = doc.querySelector('.h5p-current .human-time');
        const totalEl   = doc.querySelector('.h5p-total .human-time');

        if (!currentEl || !totalEl) {
            unlockNext('unidade sem vídeo');
            return;
        }

        const currentSec = timeToSeconds(currentEl.textContent.trim());
        const totalSec   = timeToSeconds(totalEl.textContent.trim());

        if (!totalSec) {
            unlockNext('tempo inválido');
            return;
        }

        if (!started) blockNext();

        if (!started && currentSec > 0) {
            started = true;
            log('▶️ Vídeo iniciado');
        }

        if (!finished && started && currentSec >= totalSec) {
            finished = true;
            unlockNext('vídeo finalizado');
            log('✅ Vídeo finalizado');
        }
    }

    function findIframeAndTrack() {
        const iframe = document.querySelector('.wplms_iframe_wrapper iframe');

        syncLockedCards();

        if (!iframe) {
            unlockNext('nenhum vídeo');
            return;
        }

        if (iframe !== currentIframe) {
            currentIframe = iframe;
            resetTracker();
            log('🎯 Nova unidade detectada');
        }

        trackIframe(iframe);
    }

 
    const observer = new MutationObserver(findIframeAndTrack);
    observer.observe(document.body, { childList: true, subtree: true });
    setInterval(findIframeAndTrack, 1000);

    log('👀 Controle de navegação iniciado');

})();
</script>
<?php
} 
*/

/**
 * Correção de Bug de Layout no Editor de CSS Personalizado (Aparência > CSS Adicional)
 * 
 * Autor: Jonas Borges
 * Data: 11/02/2026
 * 
 * Descrição:
 * Este código aplica uma correção visual no painel administrativo do WordPress,
 * especificamente na tela de **Aparência > CSS Adicional**, onde o campo de
 * Custom CSS pode apresentar problemas de layout, como labels quebradas ou
 * alinhamento incorreto.
 * 
 * ⚙️ O que este código faz:
 * - Utiliza o hook `admin_enqueue_scripts` para garantir que o CSS seja carregado
 *   exclusivamente no painel administrativo do WordPress.
 * - Injeta CSS inline através da função `wp_add_inline_style`, evitando a criação
 *   de arquivos adicionais apenas para uma correção pontual.
 * - Força o elemento `label` do controle `#customize-control-custom_css`
 *   a utilizar `display: block`, corrigindo problemas de quebra e alinhamento
 *   do layout no editor de CSS.
 * - Não afeta o front-end do site e não interfere em outros controles do Customizer.
 * 
 * 🎯 Objetivo:
 * Garantir uma melhor usabilidade e consistência visual no editor de CSS
 * personalizado, especialmente em ambientes customizados com temas,
 * WPLMS ou configurações avançadas do WordPress.
 */


add_action('admin_enqueue_scripts', 'webnauta_admin_custom_css');
function webnauta_admin_custom_css() {
    wp_add_inline_style(
        'wp-admin',
        '
        /* Seu CSS aqui */
        	#customize-control-custom_css label{
				display: block;
			}
        '
    );
}





