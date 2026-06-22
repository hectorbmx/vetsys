<?php

namespace App\Enums;

enum AppointmentEventType: string
{
    case Requested = 'appointment.requested';
    case CreatedManually = 'appointment.created_manually';
    case Confirmed = 'appointment.confirmed';
    case Rejected = 'appointment.rejected';
    case Proposed = 'appointment.proposed';
    case ProposalAccepted = 'appointment.proposal_accepted';
    case ProposalRejected = 'appointment.proposal_rejected';
    case ProposalExpired = 'appointment.proposal_expired';
    case Cancelled = 'appointment.cancelled';
    case Completed = 'appointment.completed';
    case NoShow = 'appointment.no_show';
    case LateFeePending = 'appointment.late_fee_pending';
    case LateFeeWaived = 'appointment.late_fee_waived';
    case LateFeeCharged = 'appointment.late_fee_charged';
}
