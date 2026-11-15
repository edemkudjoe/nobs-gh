-- Required reference data. Run this after schema.sql. It holds NO sample users and NO demo records.
-- Regions, blood groups, who can give blood to whom, the rule settings and the default stock thresholds.

INSERT INTO regions (name) VALUES ('Greater Accra'), ('Ashanti'), ('Eastern'), ('Northern'), ('Volta'), ('Western'), ('Western North'), ('Central'), ('Oti'), ('Savannah'), ('North East'), ('Upper East'), ('Upper West'), ('Bono'), ('Bono East'), ('Ahafo');

INSERT INTO blood_groups (label) VALUES ('A+'), ('A-'), ('B+'), ('B-'), ('AB+'), ('AB-'), ('O+'), ('O-');

INSERT INTO blood_compatibility (donor_group_id, recipient_group_id)

SELECT d.id, r.id FROM blood_groups d JOIN blood_groups r ON (d.label, r.label) IN (('O-','A+'), ('O-','A-'), ('O-','B+'), ('O-','B-'), ('O-','AB+'), ('O-','AB-'), ('O-','O+'), ('O-','O-'), ('O+','O+'), ('O+','A+'), ('O+','B+'), ('O+','AB+'), ('A-','A-'), ('A-','A+'), ('A-','AB-'), ('A-','AB+'), ('A+','A+'), ('A+','AB+'), ('B-','B-'), ('B-','B+'), ('B-','AB-'), ('B-','AB+'), ('B+','B+'), ('B+','AB+'), ('AB-','AB-'), ('AB-','AB+'), ('AB+','AB+'));

INSERT INTO settings (name, value) VALUES ('shelf_life_days','35'), ('min_days_routine','3'), ('near_expiry_days','7'), ('offer_hours_urgent','4'), ('offer_hours_routine','24'), ('stale_request_days','7'), ('same_region_hours','0.5'), ('organ_viable_kidney','24'), ('organ_viable_liver','8'), ('organ_viable_pancreas','12'), ('organ_viable_heart','4'), ('organ_viable_lungs','4'), ('organ_response_fraction','0.125'), ('min_age','18'), ('max_age','65'), ('min_weight_kg','50'), ('donation_gap_months','3'), ('booking_window_days','60');

INSERT INTO stock_thresholds (facility_id, blood_group_id, min_units) SELECT NULL, id, 10 FROM blood_groups;
