<?php
// dashboard/admin/assign/includes/helpers.php

function page_url($p, $q, $category, $status, $perPage) {
    $qs = [];
    if ($q !== '') $qs['q'] = $q;
    if ($category > 0) $qs['category'] = $category;
    if ($status !== '' && $status !== null) $qs['status'] = $status;
    if ($perPage) $qs['per_page'] = $perPage;
    $qs['page'] = $p;
    return './?' . http_build_query($qs);
}