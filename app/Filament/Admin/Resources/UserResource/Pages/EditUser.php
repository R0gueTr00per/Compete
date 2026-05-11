<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $profileData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('system_admin') && auth()->id() !== $this->record->id),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->record->competitorProfile;

        foreach (['first_name', 'surname', 'date_of_birth', 'gender', 'phone'] as $field) {
            $data["profile_{$field}"] = $profile?->$field;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->profileData = $this->extractProfileData($data);
        return $data;
    }

    protected function afterSave(): void
    {
        $this->profileData['profile_complete'] = $this->isProfileComplete($this->profileData);

        if ($this->record->competitorProfile) {
            $this->record->competitorProfile()->update($this->profileData);
        } elseif (! empty(array_filter($this->profileData))) {
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
