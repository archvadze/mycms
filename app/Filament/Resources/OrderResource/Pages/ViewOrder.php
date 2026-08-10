<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('contacted', 'Mark contacted')
                ->visible(
                    fn(): bool =>
                    OrderResource::canEdit($this->record)
                    && OrderResource::canTransitionStatus(
                        $this->record,
                        'contacted'
                    )
                ),
            $this->statusAction('accepted', 'Accept order')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(
                    'Accepting an order may create a project through the existing order observer.'
                )
                ->visible(
                    fn(): bool =>
                    OrderResource::canEdit($this->record)
                    && OrderResource::canTransitionStatus(
                        $this->record,
                        'accepted'
                    )
                ),
            $this->statusAction('rejected', 'Reject order')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(
                    fn(): bool =>
                    OrderResource::canEdit($this->record)
                    && OrderResource::canTransitionStatus(
                        $this->record,
                        'rejected'
                    )
                ),
            Actions\EditAction::make(),
        ];
    }

    private function statusAction(string $status, string $label): Actions\Action
    {
        return Actions\Action::make('status_' . $status)
            ->label($label)
            ->icon(match ($status) {
                'contacted' => 'heroicon-o-phone',
                'accepted' => 'heroicon-o-check-circle',
                'rejected' => 'heroicon-o-x-circle',
                default => 'heroicon-o-arrow-path',
            })
            ->color(OrderResource::statusColor($status))
            ->action(function () use ($status): void {
                /** @var Order $order */
                $order = $this->record;

                OrderResource::updateStatus($order, $status);
                $this->record->refresh();
            })
            ->successNotificationTitle(
                'Order marked ' . strtolower(OrderResource::statusLabel($status))
            );
    }
}
