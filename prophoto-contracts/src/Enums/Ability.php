<?php

namespace ProPhoto\Contracts\Enums;

enum Ability: string
{
    case VIEW_GALLERY = 'view_gallery';
    case CREATE_GALLERY = 'create_gallery';
    case EDIT_GALLERY = 'edit_gallery';
    case DELETE_GALLERY = 'delete_gallery';
    case UPLOAD_ASSET = 'upload_asset';
    case VIEW_ASSET = 'view_asset';
    case DELETE_ASSET = 'delete_asset';
    case MANAGE_ACCESS = 'manage_access';
    case MANAGE_USERS = 'manage_users';
}
