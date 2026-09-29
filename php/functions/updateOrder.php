<?php
// Gammal sparväg får inte kringgå behörighet eller CSRF-kontroll.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'Använd den nya korrigeringsvyn.']);
