<?php


enum Membership: string {
    case member = "member";
    case admin = "admin";
    case VIP = "VIP";
    case blacklisted = "blacklisted";
}

?>