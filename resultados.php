<?php

function tratar_resultados($busqueda, $actividad, $interes, $programa, $entidad, $modo)
{
    // global de WP para la base de datos
    global $wpdb;
    $busqueda = sanitize_text_field($busqueda);
    /*Los nuevos campos de búsqueda*/
    if ($actividad) {
        $actividad = sanitize_text_field($actividad);
    }
    if ($interes) {
        $interes = sanitize_text_field($interes);
    }
    if ($programa) {
        $programa = sanitize_text_field($programa);
        $programa = str_replace('&amp;', '&', $programa);
    }
    if ($entidad) {
        $entidad = sanitize_text_field($entidad);
    }
    $modo = sanitize_text_field($modo);
    $coincidences = [];

    if ($modo == 'profesionales') {
        $post_title = '1888';
    } elseif ($modo == 'stands') {
        $post_title = '2016';
    }
    // Haciendo query para lo que sea
    $results = $wpdb->get_results("SELECT id, post_content FROM {$wpdb->prefix}posts WHERE post_type = 'erforms_submission' and post_title like '%{$post_title}%'", OBJECT);

    foreach ($results as $result) {
        // decodificamos el post_content
        $result_array = json_decode($result->post_content);
        // preparamos un array asociativo con los campos de busqueda
        $searchable_array = array_search_prepare($result_array->fields_data);
        // si hay alguna coincidencia
        // guardamos el resultado para mostrar
        if (!$busqueda && !$actividad && !$interes && !$entidad && !$programa) {
            foreach ($searchable_array as $index => $string) {
                $coincidences[$result->id] = $result_array->fields_data;
            }
        } else {
            $url = false;
            $format_busqueda = clean_url_text($busqueda, $url);
            foreach ($searchable_array as $index => $string) {
                $nombre_completo = $searchable_array['nombre'] . ' ' . $searchable_array['apellidos'];
                if ($busqueda) {
                    /*Para buscar por nombre y apellidos*/
                    if (strlen($nombre_completo) > 1) {
                        $format_nombre = clean_url_text($nombre_completo, $url);
                        if ($busqueda && strpos(strtoupper($format_nombre), strtoupper($format_busqueda)) !== FALSE) {
                            $coincidences[$result->id] = $result_array->fields_data;
                        }
                    }
                    /*Para el resto de campos*/
                    $format_texto = clean_url_text($string, $url);
                    if ($busqueda && strpos(strtoupper($format_texto), strtoupper($format_busqueda)) !== FALSE) {
                        $coincidences[$result->id] = $result_array->fields_data;
                    }
                }
                /*Para buscar en los nuevos campos desplegables*/
                if ($actividad && (strpos(strtoupper($string), strtoupper($actividad)) !== FALSE)) {
                    /*Para no agregar el registro dos veces*/
                    if(!array_key_exists($result->id, $coincidences)){
                        $coincidences[$result->id] = $result_array->fields_data;
                    }
                }
                if ($interes && (strpos(strtoupper($string), strtoupper($interes)) !== FALSE)) {
                    /*Para no agregar el registro dos veces*/
                    if(!array_key_exists($result->id, $coincidences)){
                        $coincidences[$result->id] = $result_array->fields_data;
                    }
                }
                if ($programa && (strpos(strtoupper(str_replace('&amp;', '&', $string)), strtoupper($programa)) !== FALSE)) {
                    /*Para no agregar el registro dos veces*/
                    if(!array_key_exists($result->id, $coincidences)){
                        $coincidences[$result->id] = $result_array->fields_data;
                    }
                }
                if ($entidad && (strpos(strtoupper($string), strtoupper($entidad)) !== FALSE)) {
                    /*Para no agregar el registro dos veces*/
                    if(!array_key_exists($result->id, $coincidences)){
                        $coincidences[$result->id] = $result_array->fields_data;
                    }
                }
                /*Fin buscar en otros campos*/
            }
        }
    }
    return $coincidences;
}

function format_professional_results($coincidences)
{
    $html = '';
    foreach ($coincidences as $key => $coincidence) {
        $show = professional_array_prepare($coincidence);

        $show['apellidos'] = mb_strtolower($show['apellidos'], 'UTF-8');
        $show['nombre'] = mb_strtolower($show['nombre'], 'UTF-8');
        $show['empresa'] = mb_strtolower($show['empresa'], 'UTF-8');
        $show['cargo'] = str_replace ('O', 'o', $show['cargo']);
        $array_cargo = explode('/',$show['cargo']);
        $cargo = trim($array_cargo[0]);

        $show['avatar'] = get_record_avatar($show['img']);
        $html .= '<div class="div_resultados_prof"><div class="avatar"><img alt="avatar" src="' . $show['avatar'] . '" /></div><div class="datos_prof"><span class="nombre">' .
            $show["nombre"] . ' ' . $show["apellidos"] . '</span><span class="cargo">' . $cargo . '</span><span class="empresa">' .
            $show["empresa"] . '</span><span id="' . $key . '" class="mostrar_mas" onclick="mostrar_modal(' . $key . ')">Mostrar más</span></div></div>';
    }
    return $html;
}

function format_stand_results($coincidences)
{
    $html = '';
    $num_stand = 0;
    $url = true;
    foreach ($coincidences as $key => $coincidence) {
        $num_stand++;
        $show = stand_array_prepare($coincidence);
        $avatar = get_record_avatar($show['stand_logo']);

        $url = clean_url_text($show['stand_nombre'], $url);

        $html .= '<div class="div_resultados_stands"><div><a href="../arpa-feria/'.$url.'" title="' . $show["stand_nombre"] . '">'.
            '<img class="avatar_stands" alt="'. $show["stand_nombre"] . '" src="' . $avatar . '" /></a></div>'.
            '<div><p>' . $show['stand_nombre'] . '</p></div></div>';
        if ($num_stand % 4 == 0) {
            $html = $html . '<br>';
        }
    }
    return $html;
}

function search_profesional($id_profesional)
{
    global $wpdb;
    // Por defecto no hay un resultado
    $html = '<div class="warning">El profesional no existe</div>';
    $result = $wpdb->get_row("SELECT id, post_content FROM {$wpdb->prefix}posts WHERE post_type = 'erforms_submission' and post_title like '%1888%' and id = {$id_profesional}", OBJECT);
    if ($result) {
        // decodificamos el post_content
        $result_array = json_decode($result->post_content);
        // preparamos un array para mostrar un solo profesional
        $data = professional_array_prepare($result_array->fields_data);
        $data['avatar'] = get_record_avatar($data['img']);
        $sectores = '';
        foreach ($data['sector_interes'] as $sector) {
            $sectores = $sectores . $sector . ', ';
        }

        $data['apellidos'] = mb_strtolower($data['apellidos'], 'UTF-8');
        $data['nombre'] = mb_strtolower($data['nombre'], 'UTF-8');
        $data['empresa'] = mb_strtolower($data['empresa'], 'UTF-8');
        $data['cargo'] = str_replace ('O', 'o', $data['cargo']);
        $array_cargo = explode('/',$data['cargo']);
        $cargo = trim($array_cargo[0]);


        $html = '<div id="avatar_prof">
                            <img src="' . $data['avatar'] . '" class="avatar_prof" alt="avatar">
                        </div>
                        <h2 class="tit_modal">Datos del Profesional</h2>
                        <div style="margin: 0px;position: relative;top: -25px;">
                            <div class="label_popup">Nombre</div><div class="result_data" id="nombre"><span style="margin-left: 10px;">' . $data['nombre'] . '</div>
                            <div class="label_popup">Apellidos</div><div class="result_data" id="apellidos"><span style="margin-left: 10px;">' . $data['apellidos'] . '</span></div>
                            <div class="label_popup">Empresa/Institución</div><div class="result_data" id="empresa"><span style="margin-left: 10px;">' . $data['empresa'] . '</span></div>
                            <div class="label_popup">Cargo</div><div class="result_data" id="cargo"><span style="margin-left: 10px;">' . $cargo . '</span></div>
                            <div class="label_popup">Email</div><div class="result_data" id="otro_cargo"><span style="margin-left: 10px;">' . $data['email'] . '</span></div>
                            <div class="label_popup">Sector de actividad</div><div class="result_data" id="sector_actividad"><span style="margin-left: 10px;">' . $data['sector_actividad'] . '</span></div>
                            <div class="label_popup">Perfil Profesional</div><textarea disabled rows="5" cols="30" readonly id="perfil">' . $data['perfilprof'] . '</textarea>
                            <div class="label_popup">Intereses en la Bienal AR&PA 2.0</div><textarea disabled rows="5" cols="30" readonly id="intereses">' . $data['descint'] . '</textarea>
                        </div>';
    }
    return $html;
}