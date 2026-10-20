# ALGORITHM — Attendance API + Rules

```
Receive JSON → validate → auth device (hash_equals SHA256) → check active
→ validate employee active → validate time/method/direction
→ event_hash SHA256(device|emp|time|method|dir|vendor) → duplicate? 409
→ BEGIN → insert raw event → find schedule → upsert daily
→ late/early calc → last_seen + audit → COMMIT → 200 JSON
```

Late: deadline = sched_in + grace. If actual <= deadline → PRESENT, late 0.
Else LATE, late = actual − sched_in (minutes). Ex: 08:00+10, 08:17 → LATE 17; 08:07 → PRESENT 0.

Early-out: if actual_out < sched_out → early = sched_out − actual (minutes), status EARLY_OUT (keeps LATE→EARLY_OUT if both). Else 0. Ex: 17:00 vs 16:40 → 20.

Daily: the earliest IN and latest OUT for the work date are recalculated from raw events after every scan, so delayed device uploads cannot move a checkout backward. An OUT without an IN does not count as a late arrival. Raw events are never deleted. Absence is only recorded via Attendance → Finalize (after cutoff), never automatically.
