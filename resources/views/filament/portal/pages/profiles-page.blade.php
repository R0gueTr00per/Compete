<x-filament-panels::page>
    @php $profiles = $this->getProfiles(); @endphp

    {{-- Graduate form --}}
    @if ($this->graduating_profile_id)
        @php $graduatingProfile = $profiles->find($this->graduating_profile_id); @endphp
        <x-filament::section>
            <x-slot name="heading">Move "{{ $graduatingProfile?->full_name }}" to their own account</x-slot>

            <x-filament-panels::form wire:submit="graduateProfile">
                {{ $this->graduateForm }}

                <div class="flex items-center gap-3 pt-2">
                    <x-filament::button type="submit" color="warning">
                        Create account &amp; transfer profile
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="cancelEdit">
                        Cancel
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>

    {{-- Delete confirmation --}}
    @elseif ($this->deleting_profile_id)
        @php $deletingProfile = $profiles->find($this->deleting_profile_id); @endphp
        <x-filament::section>
            <x-slot name="heading">Delete "{{ $deletingProfile?->full_name }}"?</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will permanently delete the profile and cannot be undone.
            </p>

            <div class="flex items-center gap-3">
                <x-filament::button color="danger" wire:click="confirmDelete">
                    Delete profile
                </x-filament::button>
                <x-filament::button color="gray" wire:click="cancelEdit">
                    Cancel
                </x-filament::button>
            </div>
        </x-filament::section>

    {{-- Create / edit form --}}
    @elseif ($this->editing !== null)
        <x-filament::section>
            <x-slot name="heading">{{ $this->editing === 'new' ? 'Add a profile' : 'Edit profile' }}</x-slot>

            <x-filament-panels::form wire:submit="saveProfile">
                {{ $this->form }}

                <div class="flex items-center gap-3 pt-2">
                    <x-filament::button type="submit">Save</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="cancelEdit">Cancel</x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>

    {{-- Profile list --}}
    @else
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage your own profile and any family members you have registered.
            </p>
            <x-filament::button wire:click="startCreate" icon="heroicon-o-plus" size="sm">
                Add family member
            </x-filament::button>
        </div>

        @forelse ($profiles as $profile)
            @php
                $profileAccent = $profile->is_active
                    ? 'border-l-green-400 dark:border-l-green-500 profile-card-active'
                    : 'border-l-gray-300 dark:border-l-gray-600 profile-card-inactive';
            @endphp
            <x-filament::section class="mb-4 border-l-4 {{ $profileAccent }}">
                <x-slot name="heading">
                    {{ $profile->full_name }}
                    @if ($profile->profile_type === 'family_member')
                        <span class="ml-2 text-xs font-normal text-gray-400">(Family Member)</span>
                    @else
                        <span class="ml-2 text-xs font-normal text-gray-400">(Self)</span>
                    @endif
                    @unless ($profile->is_active)
                        <span class="ml-2 inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-900/30 px-2 py-0.5 text-xs font-medium text-warning-700 dark:text-warning-400">Inactive</span>
                    @endunless
                </x-slot>
                <x-slot name="headerEnd">
                    <div class="flex items-center gap-2 flex-wrap">
                        <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $profile->id }})">
                            Edit
                        </x-filament::button>
                        @if ($profile->is_active)
                            <x-filament::button size="xs" color="warning"
                                x-on:click="$wire.mountAction('deactivateProfile', { profileId: {{ $profile->id }} })">
                                Deactivate
                            </x-filament::button>
                        @else
                            <x-filament::button size="xs" color="success" wire:click="toggleActive({{ $profile->id }})">
                                Activate
                            </x-filament::button>
                        @endif
                        @if ($profile->profile_type === 'family_member' && ! $profile->hasDedicatedAccount())
                            <x-filament::button size="xs" color="primary" wire:click="startGraduate({{ $profile->id }})">
                                Move to own account
                            </x-filament::button>
                        @endif
                        @if ($profile->profile_type === 'family_member')
                            <x-filament::button size="xs" color="danger" wire:click="startDelete({{ $profile->id }})">
                                Delete
                            </x-filament::button>
                        @endif
                    </div>
                </x-slot>

                @if (! $profile->profile_complete)
                    <div class="flex items-center gap-2 rounded-lg border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-900/20 px-3 py-2 text-sm text-warning-800 dark:text-warning-200 mb-3">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0" />
                        Profile is incomplete — edit to finish filling in the required details.
                    </div>
                @endif

                <div class="flex gap-4 items-start">
                    <div class="shrink-0 hidden sm:block">
                        @if ($profile->profile_photo)
                            <img src="{{ asset('storage/' . $profile->profile_photo) }}"
                                 alt="Profile photo"
                                 class="w-14 h-18 rounded-lg object-cover border-2 border-gray-200 dark:border-gray-600" />
                        @else
                            <div class="w-14 h-18 rounded-lg bg-gray-100 dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center" style="width:3.5rem;height:4.5rem;">
                                <x-heroicon-o-user class="w-7 h-7 text-gray-400 dark:text-gray-500" />
                            </div>
                        @endif
                    </div>

                <dl class="flex-1 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    @if ($profile->date_of_birth)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date of birth</dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ tenant_date($profile->date_of_birth) }} (age {{ $profile->age }})</dd>
                        </div>
                    @endif
                    @if ($profile->gender)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gender</dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $profile->gender === 'M' ? 'Male' : 'Female' }}</dd>
                        </div>
                    @endif
                    @if ($profile->phone)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone</dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $profile->phone }}</dd>
                        </div>
                    @endif
                </dl>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="flex flex-col items-center gap-4 py-6 text-center">
                    <p class="text-gray-500">No profiles yet. Start by completing your own competitor profile.</p>
                    <x-filament::button :href="filament()->getUrl() . '/profile'" tag="a" icon="heroicon-o-user-circle">
                        Complete my profile
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endforelse
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
