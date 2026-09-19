<?php

namespace App\Modules\Notification\Templates;

/**
 * Daily fleet reminder digest — ONE email per staff member listing every
 * vehicle item that is due soon or overdue (registration, insurance, service
 * by date or km). Pure: no I/O. Items are pre-built by FleetReminderService.
 *
 * Built as an inline-styled table (email-client safe; no flex/grid).
 */
class FleetReminderDigestTemplate
{
    use FormatsNotifications;

    /**
     * @param  list<array{rego:string, vehicle:string, what:string, due:string, overdue:bool, url:string}>  $items
     */
    public function build(string $tenantName, array $items): NotificationContent
    {
        $overdue = count(array_filter($items, fn (array $i) => $i['overdue']));
        $count = count($items);

        $subject = $overdue > 0
            ? "Fleet reminder: {$overdue} overdue, ".($count - $overdue).' due soon'
            : "Fleet reminder: {$count} ".($count === 1 ? 'item' : 'items').' due soon';

        $rows = '';
        foreach ($items as $item) {
            $badge = $item['overdue']
                ? '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f6ecec;color:#7d3a3a;font-size:12px;">Overdue</span>'
                : '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f6f1e7;color:#7d5f2e;font-size:12px;">Due soon</span>';

            $rows .= '<tr>'
                .'<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">'
                .'<a href="'.e($item['url']).'" style="color:#0f172a;font-weight:bold;text-decoration:none;">'.e($item['rego']).'</a>'
                .'<div style="color:#64748b;font-size:12px;">'.e($item['vehicle']).'</div></td>'
                .'<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#334155;">'.e($item['what']).'</td>'
                .'<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#334155;white-space:nowrap;">'.e($item['due']).'</td>'
                .'<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;">'.$badge.'</td>'
                .'</tr>';
        }

        $table = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 18px;">'
            .'<tr>'
            .'<th align="left" style="padding:6px 8px;font-size:12px;color:#64748b;border-bottom:1px solid #cbd5e1;">Vehicle</th>'
            .'<th align="left" style="padding:6px 8px;font-size:12px;color:#64748b;border-bottom:1px solid #cbd5e1;">What</th>'
            .'<th align="left" style="padding:6px 8px;font-size:12px;color:#64748b;border-bottom:1px solid #cbd5e1;">Due</th>'
            .'<th style="border-bottom:1px solid #cbd5e1;"></th>'
            .'</tr>'.$rows.'</table>';

        // emailHtml escapes paragraphs; splice the (already-escaped) table in
        // after the intro paragraph.
        $intro = "Here is today's fleet summary for {$tenantName}.";
        $html = $this->emailHtml($subject, [$intro, 'Please action these before they lapse.']);
        $marker = '<p style="margin:0 0 14px;font-size:15px;line-height:1.5;color:#334155;">'.e('Please action these before they lapse.').'</p>';
        $email = str_replace($marker, $table.$marker, $html);

        $sms = "DVARO: {$count} fleet ".($count === 1 ? 'item needs' : 'items need').' attention'
            .($overdue > 0 ? " ({$overdue} overdue)" : '').'.';

        return new NotificationContent($subject, $email, $sms);
    }
}
