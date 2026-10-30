"""Terminal transport selection; no network, keys or credentials are used."""
import pathlib
import sys
import unittest
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1] / 'terminal-agent'))
from agent import Remote, local_http_host

class LocalHttpTests(unittest.TestCase):
    def test_private_hosts(self):
        for host in ('localhost', '127.0.0.1', '10.1.2.3', '172.16.0.1', '172.31.0.1', '192.168.1.20'):
            self.assertTrue(local_http_host(host))
            Remote({'url': f'http://{host}:8080'})

    def test_public_http_rejected(self):
        for host in ('example.com', '8.8.8.8', '172.15.0.1', '172.32.0.1', '192.169.1.1', '0.0.0.0'):
            self.assertFalse(local_http_host(host))
            with self.assertRaises(ValueError):
                Remote({'url': f'http://{host}:8080'})

    def test_https_preserved(self):
        self.assertEqual(Remote({'url':'https://example.com'}).url,'https://example.com/terminal_api.php')

if __name__ == '__main__':
    unittest.main()
