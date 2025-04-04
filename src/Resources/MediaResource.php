<?php

namespace Moox\Media\Resources;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction as TablesDeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Moox\Core\Traits\Base\BaseInResource;
use Moox\Media\Forms\Components\ImageDisplay;
use Moox\Media\Models\Media;
use Moox\Media\Resources\MediaResource\Pages;
use Moox\Media\Tables\Columns\CustomImageColumn;

class MediaResource extends Resource
{
    use BaseInResource;

    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'gmdi-view-timeline-o';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return config('media.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return config('media.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('media.navigation_group');
    }

    public static function form(Form $form): Form
    {
        $saveRecord = function ($state, $old, $component) use ($form) {
            $record = $form->getRecord();
            if ($state !== $old) {
                $record->{$component->getName()} = $state;
                $record->save();
            }
        };

        return $form->schema([
            ImageDisplay::make('')
                ->columnSpanFull(),

            Section::make()
                ->schema([
                    Placeholder::make('file_name')
                        ->label(__('media::fields.file_name'))
                        ->content(fn ($record) => $record->file_name),

                    Placeholder::make('mime_type')
                        ->label(__('media::fields.mime_type'))
                        ->content(fn ($record) => $record->getReadableMimeType()),

                    Placeholder::make('size')
                        ->label(__('media::fields.size'))
                        ->content(function ($record) {
                            $bytes = $record->size;
                            $units = ['B', 'KB', 'MB', 'GB'];
                            $i = 0;

                            while ($bytes >= 1024 && $i < count($units) - 1) {
                                $bytes /= 1024;
                                $i++;
                            }

                            return number_format($bytes, 2).' '.$units[$i];
                        }),

                    Placeholder::make('dimensions')
                        ->label(__('media::fields.dimensions'))
                        ->content(function ($record) {
                            $dimensions = $record->getCustomProperty('dimensions');
                            if (! $dimensions) {
                                return '-';
                            }

                            return "{$dimensions['width']} × {$dimensions['height']} Pixel";
                        })
                        ->visible(fn ($record) => str_starts_with($record->mime_type, 'image/')),

                    Placeholder::make('created_at')
                        ->label(__('media::fields.created_at'))
                        ->content(fn ($record) => $record->created_at?->format('d.m.Y H:i')),

                    Placeholder::make('updated_at')
                        ->label(__('media::fields.updated_at'))
                        ->content(fn ($record) => $record->updated_at?->format('d.m.Y H:i')),

                    Placeholder::make('uploaded_by')
                        ->label(__('media::fields.uploaded_by'))
                        ->content(function ($record) {
                            if (! $record->uploader) {
                                return '-';
                            }

                            return $record->uploader->name;
                        }),

                    Placeholder::make('usage')
                        ->label(__('media::fields.usage'))
                        ->content(function ($record) {
                            $usages = \DB::table('media_usables')
                                ->where('media_id', $record->id)
                                ->get();

                            if ($usages->isEmpty()) {
                                return __('media::fields.not_used');
                            }

                            return new \Illuminate\Support\HtmlString("
                                <button
                                    type=\"button\"
                                    x-on:click=\"\$dispatch('open-modal', { id: 'usage-modal-{$record->id}' })\"
                                    class=\"filament-button filament-button-size-sm inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-3 text-sm text-primary-600 bg-primary-50 border-primary-200 hover:bg-primary-100 focus:ring-primary-600 focus:text-primary-600 focus:bg-primary-50 focus:border-primary-600\"
                                >
                                    <span class=\"flex items-center gap-1\">
                                        <svg class=\"w-4 h-4\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\">
                                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244\" />
                                        </svg>
                                        <span>{$usages->count()} ".trans_choice('media::fields.link|links', $usages->count()).'</span>
                                    </span>
                                </button>
                            ');
                        }),

                ])
                ->columns(4),

            Section::make(__('media::fields.metadata'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('media::fields.name'))
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated($saveRecord)
                        ->disabled(fn ($record) => $record?->getOriginal('write_protected')),

                    TextInput::make('title')
                        ->label(__('media::fields.title'))
                        ->live(onBlur: true)
                        ->afterStateUpdated($saveRecord)
                        ->disabled(fn ($record) => $record?->getOriginal('write_protected')),

                    TextInput::make('alt')
                        ->label(__('media::fields.alt_text'))
                        ->live(onBlur: true)
                        ->afterStateUpdated($saveRecord)
                        ->disabled(fn ($record) => $record?->getOriginal('write_protected')),

                    Textarea::make('description')
                        ->label(__('media::fields.description'))
                        ->live(onBlur: true)
                        ->afterStateUpdated($saveRecord)
                        ->disabled(fn ($record) => $record?->getOriginal('write_protected')),
                ])
                ->columns(2)
                ->collapsed(),

            Section::make(__('media::fields.internal_note'))
                ->schema([
                    TextInput::make('internal_note')
                        ->live(onBlur: true)
                        ->afterStateUpdated($saveRecord)
                        ->disabled(fn ($record) => $record?->getOriginal('write_protected')),
                ])
                ->collapsed(),

            View::make('media::components.usage-modal'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    CustomImageColumn::make('')
                        ->alignment('center')
                        ->extraImgAttributes(function ($record, $livewire) {
                            $baseStyle = str_starts_with($record->mime_type, 'image/')
                                ? 'width: 100%; height: auto; min-width: 150px; max-width: 250px; aspect-ratio: 1/1; object-fit: cover;'
                                : 'width: 60px; height: auto; margin-top: 20px;';

                            if ($livewire->isSelecting) {
                                $style = $baseStyle.'opacity: 0.5;';

                                if (in_array($record->id, $livewire->selected)) {
                                    $style = $baseStyle.'outline: 4px solid rgb(59 130 246); opacity: 1;';
                                }

                                return [
                                    'class' => 'rounded-lg cursor-pointer',
                                    'style' => $style,
                                    'wire:click.stop' => "\$set('selected', ".
                                        (in_array($record->id, $livewire->selected)
                                            ? json_encode(array_values(array_diff($livewire->selected, [$record->id])))
                                            : json_encode(array_merge($livewire->selected, [$record->id]))
                                        ).')',
                                ];
                            }

                            return [
                                'class' => 'rounded-lg cursor-pointer',
                                'style' => $baseStyle,
                                'x-on:click' => '$wire.call("mountAction", "edit", { record: '.$record->id.' })',
                            ];
                        })
                        ->tooltip(fn ($record) => $record->title ?? __('media::fields.no_title'))
                        ->searchable(true, function (Builder $query, string $search) {
                            $query->whereHas('translations', function (Builder $query) use ($search) {
                                $query->where('locale', app()->getLocale())
                                    ->where(function (Builder $query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('title', 'like', "%{$search}%")
                                            ->orWhere('description', 'like', "%{$search}%")
                                            ->orWhere('alt', 'like', "%{$search}%")
                                            ->orWhere('internal_note', 'like', "%{$search}%");
                                    });
                            });
                        }),
                    \Filament\Tables\Columns\TextColumn::make('file_name')
                        ->label('')
                        ->alignment('center')
                        ->wrap()
                        ->limit(50)
                        ->searchable()
                        ->sortable()
                        ->visible(fn ($record) => $record && ! str_starts_with($record->mime_type ?? '', 'image/'))
                        ->extraAttributes(function ($record, $livewire) {
                            $baseStyle = 'margin-top: 10px; word-break: break-all;';

                            if ($livewire->isSelecting) {
                                return [
                                    'style' => $baseStyle,
                                    'class' => 'cursor-pointer',
                                    'wire:click.stop' => "\$set('selected', ".
                                        (in_array($record->id, $livewire->selected)
                                            ? json_encode(array_values(array_diff($livewire->selected, [$record->id])))
                                            : json_encode(array_merge($livewire->selected, [$record->id]))
                                        ).')',
                                ];
                            }

                            return [
                                'style' => $baseStyle,
                                'class' => 'cursor-pointer',
                                'x-on:click' => '$wire.call("mountAction", "edit", { record: '.$record->id.' })',
                            ];
                        }),
                ]),
            ])
            ->headerActionsPosition(HeaderActionsPosition::Bottom)
            ->headerActions([
                \Filament\Tables\Actions\Action::make('toggleSelect')
                    ->label(function ($livewire) {
                        return $livewire->isSelecting
                            ? __('media::fields.end_selection')
                            : __('media::fields.select_multiple');
                    })
                    ->icon(fn ($livewire) => $livewire->isSelecting ? 'heroicon-m-x-mark' : 'heroicon-m-squares-2x2')
                    ->color(fn ($livewire) => $livewire->isSelecting ? 'gray' : 'primary')
                    ->action(function ($livewire) {
                        $livewire->isSelecting = ! $livewire->isSelecting;
                        $livewire->selected = [];
                    }),

                \Filament\Tables\Actions\Action::make('deleteSelected')
                    ->label(function ($livewire) {
                        $count = count($livewire->selected);

                        return $count > 0
                            ? __('media::fields.delete_selected')." ({$count} ".trans_choice('media::fields.file|files', $count).')'
                            : __('media::fields.delete_selected');
                    })
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(function ($livewire) {
                        $count = count($livewire->selected);

                        return __('media::fields.delete_selected')." ({$count} ".trans_choice('media::fields.file|files', $count).')';
                    })
                    ->modalDescription(__('media::fields.delete_confirmation'))
                    ->modalSubmitActionLabel(__('media::fields.yes_delete'))
                    ->modalCancelActionLabel(__('media::fields.cancel'))
                    ->visible(fn ($livewire) => $livewire->isSelecting && ! empty($livewire->selected))
                    ->action(function ($livewire) {
                        $successCount = 0;
                        $errorCount = 0;
                        $protectedCount = 0;

                        foreach ($livewire->selected as $id) {
                            try {
                                $media = Media::find($id);
                                if (! $media) {
                                    continue;
                                }

                                if (! auth()->user()->can('delete', $media)) {
                                    $protectedCount++;

                                    continue;
                                }

                                if ($media->getOriginal('write_protected')) {
                                    $protectedCount++;

                                    continue;
                                }

                                $media->deletePreservingMedia();
                                $media->delete();
                                $successCount++;
                            } catch (\Exception $e) {
                                Log::error('Media deletion failed: '.$e->getMessage(), [
                                    'media_id' => $id,
                                ]);
                                $errorCount++;
                            }
                        }

                        if ($successCount > 0) {
                            Notification::make()
                                ->success()
                                ->title($successCount.' '.trans_choice('media::fields.file_deleted|files_deleted', $successCount))
                                ->send();
                        }

                        if ($protectedCount > 0) {
                            Notification::make()
                                ->warning()
                                ->title(__('media::fields.protected_skipped'))
                                ->body($protectedCount.' '.trans_choice('media::fields.protected_file_skipped|protected_files_skipped', $protectedCount))
                                ->persistent()
                                ->send();
                        }

                        if ($errorCount > 0) {
                            Notification::make()
                                ->danger()
                                ->title(__('media::fields.delete_error'))
                                ->body($errorCount.' '.trans_choice('media::fields.file_could_not_be_deleted|files_could_not_be_deleted', $errorCount))
                                ->persistent()
                                ->send();
                        }

                        $livewire->isSelecting = false;
                        $livewire->selected = [];
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->icon('')
                    ->label('')
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('media::fields.cancel'))
                    ->authorize('view')
                    ->extraModalFooterActions([
                        TablesDeleteAction::make()
                            ->label(__('media::fields.delete_file'))
                            ->color('danger')
                            ->icon('heroicon-m-trash')
                            ->requiresConfirmation()
                            ->modalHeading(fn ($record) => __('media::fields.delete_file_heading', ['title' => $record->title ?: $record->name]))
                            ->modalDescription(__('media::fields.delete_file_description'))
                            ->modalSubmitActionLabel(__('media::fields.yes_delete'))
                            ->modalCancelActionLabel(__('media::fields.cancel'))
                            ->hidden(fn (Media $record) => ! auth()->user()->can('delete', $record) || $record->getOriginal('write_protected'))
                            ->before(function ($record) {
                                try {
                                    if ($record->getOriginal('write_protected')) {
                                        Notification::make()
                                            ->danger()
                                            ->title(__('media::fields.delete_error'))
                                            ->body(__('media::fields.protected_file_error'))
                                            ->persistent()
                                            ->send();

                                        return false;
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->danger()
                                        ->title(__('media::fields.delete_error'))
                                        ->body($e->getMessage())
                                        ->persistent()
                                        ->send();

                                    return false;
                                }
                            })
                            ->action(function ($record) {
                                try {
                                    $fileName = $record->file_name;
                                    $record->deletePreservingMedia();
                                    $record->delete();

                                    Notification::make()
                                        ->success()
                                        ->title(__('media::fields.delete_file_success', ['fileName' => $fileName]))
                                        ->send();

                                    return redirect(static::getUrl('index'));
                                } catch (\Exception $e) {
                                    Log::error('Media deletion failed: '.$e->getMessage(), [
                                        'media_id' => $record->id,
                                        'file_name' => $record->file_name,
                                    ]);

                                    Notification::make()
                                        ->danger()
                                        ->title(__('media::fields.delete_error'))
                                        ->body(__('media::fields.delete_file_error', ['fileName' => $record->file_name]))
                                        ->send();

                                    return null;
                                }
                            }),
                        \Filament\Tables\Actions\Action::make('download')
                            ->label(__('media::fields.download_file'))
                            ->icon('heroicon-m-arrow-down-tray')
                            ->action(function (Media $record) {
                                return response()->download(
                                    $record->getPath(),
                                    $record->file_name,
                                    ['Content-Type' => $record->mime_type]
                                );
                            }),
                    ]),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label(__('media::fields.mime_type'))
                    ->options([
                        'images' => __('media::fields.images'),
                        'videos' => __('media::fields.videos'),
                        'audios' => __('media::fields.audios'),
                        'documents' => __('media::fields.documents'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        $mimeTypes = [
                            'images' => [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/svg+xml',
                                'image/gif',
                                'image/bmp',
                                'image/tiff',
                                'image/ico',
                                'image/heic',
                                'image/heif',
                                'image/x-icon',
                                'image/vnd.microsoft.icon',
                            ],
                            'videos' => [
                                'video/mp4',
                                'video/webm',
                                'video/quicktime',
                                'video/x-msvideo',
                                'video/x-matroska',
                                'video/3gpp',
                                'video/x-flv',
                            ],
                            'audios' => [
                                'audio/mpeg',
                                'audio/ogg',
                                'audio/wav',
                                'audio/webm',
                                'audio/aac',
                                'audio/midi',
                                'audio/x-midi',
                                'audio/mp4',
                                'audio/flac',
                            ],
                            'documents' => [
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'application/rtf',
                                'text/plain',
                                'text/csv',
                                'text/html',
                                'text/xml',
                                'application/json',
                                'application/x-yaml',
                                'application/zip',
                                'application/x-zip-compressed',
                                'application/x-rar-compressed',
                                'application/x-7z-compressed',
                                'application/gzip',
                                'application/x-tar',
                            ],
                        ];

                        if (isset($mimeTypes[$data['value']])) {
                            $query->whereIn('mime_type', $mimeTypes[$data['value']]);
                        }

                        return $query;
                    }),

                SelectFilter::make('uploader')
                    ->label(__('media::fields.uploaded_by'))
                    ->options(function () {
                        $uploaderTypes = Media::query()
                            ->distinct()
                            ->whereNotNull('uploader_type')
                            ->pluck('uploader_type')
                            ->toArray();

                        $options = [];

                        foreach ($uploaderTypes as $type) {
                            /** @var \Illuminate\Database\Eloquent\Collection<int, Media> $mediaItems */
                            $mediaItems = Media::query()
                                ->where('uploader_type', $type)
                                ->whereNotNull('uploader_id')
                                ->with('uploader')
                                ->get();

                            /** @var array<string, string> $uploaders */
                            $uploaders = $mediaItems
                                ->map(function (Media $media): ?array {
                                    $uploader = $media->uploader;
                                    if ($uploader && method_exists($uploader, 'getName')) {
                                        return [
                                            'id' => $media->uploader_type.'::'.$media->uploader_id,
                                            'name' => $uploader->getName(),
                                        ];
                                    }
                                    if ($uploader && isset($uploader->name)) {
                                        return [
                                            'id' => $media->uploader_type.'::'.$media->uploader_id,
                                            'name' => $uploader->name,
                                        ];
                                    }

                                    return null;
                                })
                                ->filter()
                                ->unique(fn (array $item): string => $item['id'])
                                ->pluck('name', 'id')
                                ->toArray();

                            if (! empty($uploaders)) {
                                $typeName = class_basename($type);
                                $options[$typeName] = $uploaders;
                            }
                        }

                        return $options;
                    })
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        $parts = explode('::', $data['value']);
                        if (count($parts) !== 2) {
                            return $query;
                        }

                        return $query
                            ->where('uploader_type', $parts[0])
                            ->where('uploader_id', $parts[1]);
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('date')
                    ->label(__('media::fields.uploaded'))
                    ->options([
                        'today' => __('media::fields.today'),
                        'week' => __('media::fields.week'),
                        'month' => __('media::fields.month'),
                        'year' => __('media::fields.year'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        switch ($data['value']) {
                            case 'today':
                                $query->whereDate('created_at', today());
                                break;
                            case 'week':
                                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                                break;
                            case 'month':
                                $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                                break;
                            case 'year':
                                $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                                break;
                        }

                        return $query;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([30, 60, 90])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 5,
                '2xl' => 6,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
        ];
    }
}
