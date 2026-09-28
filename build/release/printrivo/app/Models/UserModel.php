<?php
namespace App\Models;
class UserModel extends \CodeIgniter\Shield\Models\UserModel
{
    public function createNewUser(array $data = []): \CodeIgniter\Shield\Entities\User
    {
        // Save the promotion selection only; billing must apply it at checkout later.
        if (isset($data['promo_code'])) {
            $data['promo_code'] = strtoupper(trim($data['promo_code'])) ?: null;
        }
        return parent::createNewUser($data);
    }

    protected function initialize(): void
    {
        parent::initialize();
        $this->allowedFields = array_merge($this->allowedFields, ['first_name', 'last_name', 'promo_code']);
    }
}
