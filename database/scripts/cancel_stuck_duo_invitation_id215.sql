-- One-time cleanup: cancel stuck "waiting" Duo invitation id=215
-- Player LeFunny (user_id=3, code=SB-TEQN) had this invite stuck in "waiting"
-- status to Emi D (player2_id=4). Run once on the production database.
UPDATE duo_matches
SET status = 'cancelled'
WHERE id = 215
  AND status = 'waiting';
