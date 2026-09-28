<?php
namespace App\Controllers;
class Register extends \CodeIgniter\Shield\Controllers\RegisterController
{
    protected function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        unset($rules['username']);
        $rules['first_name'] = ['label' => 'First name', 'rules' => ['required', 'max_length[80]']];
        $rules['last_name'] = ['label' => 'Last name', 'rules' => ['required', 'max_length[80]']];
        $rules['promo_code'] = ['label' => 'Discount code', 'rules' => ['permit_empty', 'max_length[32]', 'regex_match[/\A\s*(?i:alpha)\s*\z/]'], 'errors' => ['regex_match' => 'Enter Alpha for the early-adopter offer, or leave the code blank.']];
        return $rules;
    }
}
