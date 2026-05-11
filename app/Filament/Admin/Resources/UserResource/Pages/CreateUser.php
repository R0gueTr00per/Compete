<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $role = 'user';
    private array $profileData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role        = $data['role'] ?? 'user';
        $this->profileData = $this->extractProfileData($data);

        unset($data['role']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);

        if (! empty(array_filter($this->profileData))) {
            $this->profileData['profile_complete'] = $this->isProfileComplete($this->profileData);
            $this->record->competitorProfile()->create($this->profileData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function extractProfileData(array &$data): array
    {
        $profile = [];
        foreach (['first_name', 'surname', 'date_of_birth', 'gender', 'phone'] as $field) {
            $key = "profile_{$field}";
            if (array_key_exists($key, $data)) {
                $profile[$field] = $data[$key] ?: null;
                unset($data[$key]);
            }
        }
        return $profile;
    }

    private function isProfileComplete(array $profile): bool
    {
        return filled($profile['first_name'] ?? null)
            && filled($profile['surname'] ?? null)
            && filled($profile['date_of_birth'] ?? null)
            && filled($profile['gender'] ?? null);
    }
}
